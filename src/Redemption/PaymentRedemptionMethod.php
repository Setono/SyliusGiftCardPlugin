<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

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
    private const DETAIL_GIFT_CARD_ID = 'setono_gift_card_id';

    private const DETAIL_GIFT_CARD_CODE = 'setono_gift_card_code';

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
    ) {
        parent::__construct($orderProcessor, $coverageCalculator);
    }

    public function commit(OrderInterface $order): void
    {
        $channel = $order->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $currencyCode = $order->getCurrencyCode();
        Assert::notNull($currencyCode);

        $paymentMethod = null;

        foreach ($this->coverageCalculator->calculate($order)->getEntries() as $entry) {
            $giftCard = $entry['giftCard'];
            $amount = $entry['amount'];

            if ($amount <= 0 || $this->hasPaymentForGiftCard($order, $giftCard)) {
                continue;
            }

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
