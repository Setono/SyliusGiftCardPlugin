<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Exception\UnderpaidOrderException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Each applied gift card becomes a real, completed Sylius payment at order placement, so the order total stays
 * intact and only the remaining amount is charged through the normal gateway. A gift card settles a liability
 * the shop already took payment for, which is a payment rather than a reduction in what the order is worth
 */
final class PaymentRedemptionMethod extends RedemptionMethod
{
    use ORMTrait;

    private const DETAIL_GIFT_CARD_ID = 'setono_gift_card_id';

    private const DETAIL_GIFT_CARD_CODE = 'setono_gift_card_code';

    /** MariaDB's "Record has changed since last read", raised by a locking read under innodb_snapshot_isolation */
    private const ER_CHECKREAD = 1020;

    /**
     * @param FactoryInterface<PaymentInterface> $paymentFactory
     */
    public function __construct(
        OrderProcessorInterface $orderProcessor,
        GiftCardCoverageCalculatorInterface $coverageCalculator,
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        private readonly GiftCardPaymentMethodProviderInterface $paymentMethodProvider,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
        private readonly FactoryInterface $paymentFactory,
        private readonly StateMachineInterface $stateMachine,
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        ManagerRegistry $managerRegistry,
    ) {
        parent::__construct($orderProcessor, $coverageCalculator);

        $this->managerRegistry = $managerRegistry;
    }

    public function commit(OrderInterface $order): void
    {
        $channel = $order->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $currencyCode = $order->getCurrencyCode();
        Assert::notNull($currencyCode);

        $entries = array_values(array_filter(
            $this->coverageCalculator->calculate($order)->getEntries(),
            fn (array $entry): bool => $entry['amount'] > 0 && !$this->hasPaymentForGiftCard($order, $entry['giftCard']),
        ));

        $this->lockGiftCards(array_column($entries, 'giftCard'));

        $paymentMethod = null;

        foreach ($entries as ['giftCard' => $giftCard, 'amount' => $amount]) {
            $paymentMethod ??= $this->paymentMethodProvider->getPaymentMethod($channel);

            $payment = $this->paymentFactory->createNew();
            $payment->setMethod($paymentMethod);
            $payment->setCurrencyCode($currencyCode);
            $payment->setAmount($amount);
            $payment->setDetails([
                self::DETAIL_GIFT_CARD_ID => $giftCard->getId(),
                self::DETAIL_GIFT_CARD_CODE => $giftCard->getCode(),
            ]);

            $order->addPayment($payment);

            $this->transition($payment, PaymentTransitions::TRANSITION_CREATE);
            $this->transition($payment, PaymentTransitions::TRANSITION_COMPLETE);

            $this->balanceOperator->redeem(
                $giftCard,
                $amount,
                $order,
                $payment,
                sprintf('redeem:order:%s:gift_card:%s', (string) $order->getId(), (string) $giftCard->getId()),
            );
        }

        $this->assertPaidInFull($order);
    }

    public function rollback(OrderInterface $order): void
    {
        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface || !$this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            // Refunding the payment is what gives the balance back: rollbackPayment() runs off the refund transition
            // on both state machine adapters, so a cancelled order and an admin refunding the payment by hand take the
            // same path and neither can restore what the other already has. Restoring here as well would not be
            // caught by the idempotency key, which is only visible to the next call once the ledger row is flushed.
            // Only a completed payment can be refunded, and only a completed payment has anything to give back
            $this->transition($payment, PaymentTransitions::TRANSITION_REFUND);
        }
    }

    public function rollbackPayment(PaymentInterface $payment): void
    {
        if (!$this->paymentChecker->isGiftCardPayment($payment)) {
            return;
        }

        // A payment still standing has not given the money back, so the card must not get it back either
        if (PaymentInterface::STATE_REFUNDED !== $payment->getState()) {
            return;
        }

        $giftCard = $this->resolveGiftCard($payment);
        if (null === $giftCard) {
            return;
        }

        $order = $payment->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        // Keyed on the payment alone, so every route to refunding it (rollback() on cancel, the admin's refund button,
        // a re-fired callback) shares one key and the balance comes back exactly once per payment
        $this->balanceOperator->restore(
            $giftCard,
            (int) $payment->getAmount(),
            $order,
            $payment,
            sprintf('restore:payment:%s', (string) $payment->getId()),
        );
    }

    /**
     * A placed order must never carry less payment than its total. Coverage is computed from the cards' live
     * balances, so a card spent elsewhere, disabled or adjusted since it was applied pays less here than the gateway
     * payment was sized (or the payment step skipped) for, and quietly carrying on would place the order underpaid,
     * which Sylius then marks paid if it finds no payments at all. The checkout guard catches this before the
     * transition; this is the last line of defence for whatever reaches commit another way. Idempotent: on a repeat
     * call the gift card payments created the first time round count instead of the balances they consumed
     *
     * @throws UnderpaidOrderException
     */
    private function assertPaidInFull(OrderInterface $order): void
    {
        // an order that never involved a gift card is Sylius' business
        if (!$order->hasGiftCards()) {
            return;
        }

        $paid = 0;
        foreach ($order->getPayments() as $payment) {
            if (in_array($payment->getState(), [
                BasePaymentInterface::STATE_CANCELLED,
                BasePaymentInterface::STATE_FAILED,
                BasePaymentInterface::STATE_REFUNDED,
            ], true)) {
                continue;
            }

            $paid += (int) $payment->getAmount();
        }

        if ($paid < $order->getTotal()) {
            throw new UnderpaidOrderException($order, $paid);
        }
    }

    /**
     * Two orders redeeming the same card at the same moment would otherwise deadlock instead of racing on the card's
     * version. Doctrine inserts before it updates, so each order's ledger row takes a shared lock on the card's row
     * through its foreign key, and each order then needs an exclusive lock on that row for the versioned update:
     * neither can have it while the other holds its shared lock, and the database rolls one of them back with a
     * DeadlockException that nothing turns into a redirect.
     *
     * Taking the exclusive lock before anything of the order is written makes the second order wait for the first
     * to commit instead. Its versioned update then matches no row, which is the OptimisticLockException Sylius'
     * update handler already turns into a redirect; where the database reports the loss at the lock instead, the
     * same exception is thrown from here. The cards are locked in id order, so two orders sharing more than one
     * card cannot deadlock on these locks either.
     *
     * A row lock lasts until the end of the transaction it is taken in. At checkout completion that is the one
     * Sylius' update handler wraps the transition and the flush in; without a transaction there is nothing that
     * would hold the lock until the flush, so none is taken
     *
     * @param list<GiftCardInterface> $giftCards
     */
    private function lockGiftCards(array $giftCards): void
    {
        usort($giftCards, static fn (GiftCardInterface $a, GiftCardInterface $b): int => $a->getId() <=> $b->getId());

        foreach ($giftCards as $giftCard) {
            $manager = $this->getManager($giftCard);

            if (!$manager->getConnection()->isTransactionActive() || !$manager->contains($giftCard)) {
                continue;
            }

            try {
                $manager->lock($giftCard, LockMode::PESSIMISTIC_WRITE);
            } catch (\Throwable $e) {
                // lock() only declares Doctrine's own lock exceptions, but the SELECT ... FOR UPDATE it runs fails
                // with whatever the database reports
                if ($e instanceof DriverException && self::lostToAnotherWriter($e)) {
                    throw OptimisticLockException::lockFailed($giftCard);
                }

                throw $e;
            }
        }
    }

    /**
     * Some databases report the other order's win at the lock rather than at the versioned update. MariaDB from
     * 11.6.2 checks locking reads against the transaction's snapshot by default (innodb_snapshot_isolation), so when
     * the other order committed after this one started reading, the lock fails with ER_CHECKREAD instead of waiting
     * and succeeding. And a writer that did not lock first (an admin adjusting the balance, a cancelled order
     * restoring it) can hold the shared lock of its own ledger row while this order waits for the exclusive one;
     * the database then breaks the deadlock by rolling this order back. Either way the card changed under the order,
     * which is what the versioned update would have reported
     */
    private static function lostToAnotherWriter(DriverException $exception): bool
    {
        return $exception instanceof DeadlockException || self::ER_CHECKREAD === $exception->getCode();
    }

    private function hasPaymentForGiftCard(OrderInterface $order, GiftCardInterface $giftCard): bool
    {
        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface || !$this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            if (($payment->getDetails()[self::DETAIL_GIFT_CARD_ID] ?? null) === $giftCard->getId()) {
                return true;
            }
        }

        return false;
    }

    private function resolveGiftCard(PaymentInterface $payment): ?GiftCardInterface
    {
        $code = $payment->getDetails()[self::DETAIL_GIFT_CARD_CODE] ?? null;
        if (!is_string($code)) {
            return null;
        }

        return $this->giftCardRepository->findOneByCode($code);
    }

    private function transition(BasePaymentInterface $payment, string $transition): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
        }
    }
}
