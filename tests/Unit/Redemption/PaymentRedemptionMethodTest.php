<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Redemption;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\PaymentRedemptionMethod;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Two orders redeeming the same card must lose to each other on the card, in a way Sylius turns into a redirect,
 * rather than deadlock: the commit tests pin the locking commit() does to get there.
 *
 * Refunding a gift card payment is the one place its balance comes back from, whether an admin refunded it by hand
 * or the order was cancelled, so rollbackPayment() has to give back exactly what the payment took, keyed on the
 * payment alone, and only once the payment really is refunded
 */
final class PaymentRedemptionMethodTest extends TestCase
{
    use ProphecyTrait;

    /** @var list<string> */
    private array $log = [];

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<Connection> */
    private ObjectProphecy $connection;

    /** @var ObjectProphecy<GiftCardBalanceOperatorInterface> */
    private ObjectProphecy $balanceOperator;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    /** @var ObjectProphecy<StateMachineInterface> */
    private ObjectProphecy $stateMachine;

    /** @var ObjectProphecy<GiftCardRepositoryInterface> */
    private ObjectProphecy $giftCardRepository;

    protected function setUp(): void
    {
        $this->log = [];
        $this->connection = $this->prophesize(Connection::class);
        $this->connection->isTransactionActive()->willReturn(true);

        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->manager->getConnection()->willReturn($this->connection->reveal());
        $this->manager->contains(Argument::any())->willReturn(true);

        $this->balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $this->stateMachine = $this->prophesize(StateMachineInterface::class);
        $this->giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
    }

    /** @test */
    public function it_locks_the_gift_cards_in_id_order_before_redeeming_any_of_them(): void
    {
        $log = &$this->log;
        $this->manager->lock(Argument::type(GiftCardInterface::class), LockMode::PESSIMISTIC_WRITE)->will(
            function (array $args) use (&$log): void {
                /** @var GiftCardInterface $giftCard */
                $giftCard = $args[0];
                $log[] = 'lock ' . (string) $giftCard->getId();
            },
        );

        $this->redemptionMethod([[$this->giftCard(2), 2000], [$this->giftCard(1), 3000]])->commit($this->order());

        self::assertSame(['lock 1', 'lock 2', 'redeem 2', 'redeem 1'], $this->log);
    }

    /**
     * A lock only lasts until the end of the transaction it is taken in, so without one there is nothing to take
     *
     * @test
     */
    public function it_takes_no_lock_outside_a_transaction(): void
    {
        $this->connection->isTransactionActive()->willReturn(false);
        $this->manager->lock(Argument::cetera())->shouldNotBeCalled();

        $this->redemptionMethod([[$this->giftCard(1), 3000]])->commit($this->order());

        self::assertSame(['redeem 1'], $this->log);
    }

    /**
     * A writer that did not lock first (an admin adjusting the balance, a cancelled order restoring it) can hold the
     * shared lock of its own ledger row while this order waits for the exclusive one, and the database rolls this
     * order back to break the deadlock
     *
     * @test
     */
    public function it_reports_a_deadlock_at_the_lock_as_a_lost_race_on_the_gift_card(): void
    {
        $giftCard = $this->giftCard(1);
        $this->manager->lock($giftCard, LockMode::PESSIMISTIC_WRITE)->willThrow(
            new DeadlockException(self::driverException(1213, '40001'), null),
        );

        $this->assertLostRaceOn($giftCard);
    }

    /**
     * Only a deadlock, which Doctrine reports the same way on every database, is a lost race on the card. Any other
     * error the database reports is left for whatever handles database errors
     *
     * @test
     */
    public function it_lets_any_other_database_error_through(): void
    {
        $giftCard = $this->giftCard(1);
        $timeout = new LockWaitTimeoutException(self::driverException(1205, 'HY000'), null);
        $this->manager->lock($giftCard, LockMode::PESSIMISTIC_WRITE)->willThrow($timeout);

        try {
            $this->redemptionMethod([[$giftCard, 3000]])->commit($this->order());

            self::fail('The database error was swallowed');
        } catch (LockWaitTimeoutException $e) {
            self::assertSame($timeout, $e);
        }

        self::assertSame([], $this->log);
    }

    /** @test */
    public function it_restores_what_a_refunded_gift_card_payment_took_keyed_on_the_payment(): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();
        $giftCard = $this->prophesize(GiftCardInterface::class)->reveal();
        $payment = $this->giftCardPayment(42, PaymentInterface::STATE_REFUNDED, 3000, 'CARD0001', $order);

        $this->giftCardRepository->findOneByCode('CARD0001')->willReturn($giftCard);

        $this->balanceOperator->restore($giftCard, 3000, $order, $payment, 'restore:payment:42')->shouldBeCalledOnce();

        $this->rollbackRedemptionMethod()->rollbackPayment($payment);
    }

    /** @test */
    public function it_does_not_restore_for_a_payment_that_is_not_a_gift_card_payment(): void
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getState()->willReturn(PaymentInterface::STATE_REFUNDED);
        $this->paymentChecker->isGiftCardPayment($payment)->willReturn(false);

        $this->balanceOperator->restore(Argument::cetera())->shouldNotBeCalled();

        $this->rollbackRedemptionMethod()->rollbackPayment($payment->reveal());
    }

    /**
     * A payment that is still standing has not given the money back, so the card must not get it back either
     *
     * @test
     */
    public function it_does_not_restore_for_a_gift_card_payment_that_is_not_refunded(): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();
        $payment = $this->giftCardPayment(42, PaymentInterface::STATE_COMPLETED, 3000, 'CARD0001', $order);

        $this->balanceOperator->restore(Argument::cetera())->shouldNotBeCalled();

        $this->rollbackRedemptionMethod()->rollbackPayment($payment);
    }

    /**
     * Rolling back an order refunds its gift card payments and leaves restoring to the refund hook, so the balance
     * cannot come back twice; a payment that cannot be refunded (not completed, or refunded already) is left alone
     *
     * @test
     */
    public function it_rolls_back_an_order_by_refunding_its_gift_card_payments(): void
    {
        $order = $this->prophesize(OrderInterface::class);

        $completed = $this->giftCardPayment(1, PaymentInterface::STATE_COMPLETED, 3000, 'CARD0001', $order->reveal());
        $refunded = $this->giftCardPayment(2, PaymentInterface::STATE_REFUNDED, 2000, 'CARD0002', $order->reveal());

        $gateway = $this->prophesize(PaymentInterface::class);
        $gateway->getState()->willReturn(PaymentInterface::STATE_COMPLETED);
        $this->paymentChecker->isGiftCardPayment($gateway)->willReturn(false);

        $order->getPayments()->willReturn(new ArrayCollection([$completed, $refunded, $gateway->reveal()]));

        $this->stateMachine->can($completed, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->willReturn(true);
        $this->stateMachine->can($refunded, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->willReturn(false);

        $this->stateMachine->apply($completed, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->shouldBeCalledOnce();
        $this->stateMachine->apply($refunded, Argument::cetera())->shouldNotBeCalled();
        $this->stateMachine->apply($gateway, Argument::cetera())->shouldNotBeCalled();

        $this->balanceOperator->restore(Argument::cetera())->shouldNotBeCalled();

        $this->rollbackRedemptionMethod()->rollback($order->reveal());
    }

    private function assertLostRaceOn(GiftCardInterface $giftCard): void
    {
        try {
            $this->redemptionMethod([[$giftCard, 3000]])->commit($this->order());

            self::fail('The lost race went unnoticed');
        } catch (OptimisticLockException $e) {
            // Sylius' update handler turns this into a redirect, and it names the card the order lost
            self::assertSame($giftCard, $e->getEntity());
        }

        self::assertSame([], $this->log, 'Nothing may be redeemed from a card the order lost');
    }

    /**
     * Built for the commit tests: the coverage is what the given cards cover, and every redemption is logged
     *
     * @param list<array{0: GiftCardInterface, 1: int}> $coverage
     */
    private function redemptionMethod(array $coverage): PaymentRedemptionMethod
    {
        $coverageCalculator = $this->prophesize(GiftCardCoverageCalculatorInterface::class);
        $coverageCalculator->calculate(Argument::any())->willReturn(new GiftCardCoverage(array_map(
            static fn (array $entry): array => ['giftCard' => $entry[0], 'amount' => $entry[1]],
            $coverage,
        )));

        $log = &$this->log;
        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->redeem(Argument::cetera())->will(function (array $args) use (&$log): void {
            /** @var GiftCardInterface $giftCard */
            $giftCard = $args[0];
            $log[] = 'redeem ' . (string) $giftCard->getId();
        });

        $paymentMethodProvider = $this->prophesize(GiftCardPaymentMethodProviderInterface::class);
        $paymentMethodProvider->getPaymentMethod(Argument::any())->willReturn(
            $this->prophesize(PaymentMethodInterface::class)->reveal(),
        );

        /** @var ObjectProphecy<FactoryInterface<PaymentInterface>> $paymentFactory */
        $paymentFactory = $this->prophesize(FactoryInterface::class);
        $paymentFactory->createNew()->will(static fn (): Payment => new Payment());

        // the payment transitions are Sylius' business, not what these tests are about
        $stateMachine = $this->prophesize(StateMachineInterface::class);
        $stateMachine->can(Argument::cetera())->willReturn(false);

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->willReturn($this->manager->reveal());

        return new PaymentRedemptionMethod(
            $this->prophesize(OrderProcessorInterface::class)->reveal(),
            $coverageCalculator->reveal(),
            $balanceOperator->reveal(),
            $paymentMethodProvider->reveal(),
            $this->prophesize(GiftCardPaymentCheckerInterface::class)->reveal(),
            $paymentFactory->reveal(),
            $stateMachine->reveal(),
            $this->prophesize(GiftCardRepositoryInterface::class)->reveal(),
            $managerRegistry->reveal(),
        );
    }

    /**
     * Built for the rollback tests, around the prophecies they set their expectations on
     */
    private function rollbackRedemptionMethod(): PaymentRedemptionMethod
    {
        /** @var ObjectProphecy<FactoryInterface<PaymentInterface>> $paymentFactory */
        $paymentFactory = $this->prophesize(FactoryInterface::class);

        return new PaymentRedemptionMethod(
            $this->prophesize(OrderProcessorInterface::class)->reveal(),
            $this->prophesize(GiftCardCoverageCalculatorInterface::class)->reveal(),
            $this->balanceOperator->reveal(),
            $this->prophesize(GiftCardPaymentMethodProviderInterface::class)->reveal(),
            $this->paymentChecker->reveal(),
            $paymentFactory->reveal(),
            $this->stateMachine->reveal(),
            $this->giftCardRepository->reveal(),
            $this->prophesize(ManagerRegistry::class)->reveal(),
        );
    }

    private function giftCard(int $id): GiftCardInterface
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getId()->willReturn($id);
        $giftCard->getCode()->willReturn('CARD' . $id);

        return $giftCard->reveal();
    }

    private function giftCardPayment(int $id, string $state, int $amount, string $giftCardCode, OrderInterface $order): PaymentInterface
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getId()->willReturn($id);
        $payment->getState()->willReturn($state);
        $payment->getAmount()->willReturn($amount);
        $payment->getOrder()->willReturn($order);
        $payment->getDetails()->willReturn([
            'setono_gift_card_id' => 7,
            'setono_gift_card_code' => $giftCardCode,
        ]);

        $this->paymentChecker->isGiftCardPayment($payment)->willReturn(true);

        return $payment->reveal();
    }

    private function order(): Order
    {
        $order = new Order();
        $order->setChannel($this->prophesize(ChannelInterface::class)->reveal());
        $order->setCurrencyCode('USD');

        return $order;
    }

    private static function driverException(int $code, string $sqlState): AbstractException
    {
        return new class('Simulated database error', $sqlState, $code) extends AbstractException {
        };
    }
}
