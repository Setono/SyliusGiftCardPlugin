<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Redemption;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\PaymentRedemptionMethod;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Refunding a gift card payment is the one place its balance comes back from, whether an admin refunded it by hand
 * or the order was cancelled, so rollbackPayment() has to give back exactly what the payment took, keyed on the
 * payment alone, and only once the payment really is refunded
 */
final class PaymentRedemptionMethodTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardBalanceOperatorInterface> */
    private ObjectProphecy $balanceOperator;

    /** @var ObjectProphecy<GiftCardPaymentCheckerInterface> */
    private ObjectProphecy $paymentChecker;

    /** @var ObjectProphecy<StateMachineInterface> */
    private ObjectProphecy $stateMachine;

    /** @var ObjectProphecy<GiftCardRepositoryInterface> */
    private ObjectProphecy $giftCardRepository;

    private PaymentRedemptionMethod $redemptionMethod;

    protected function setUp(): void
    {
        $this->balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $this->paymentChecker = $this->prophesize(GiftCardPaymentCheckerInterface::class);
        $this->stateMachine = $this->prophesize(StateMachineInterface::class);
        $this->giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);

        /** @var ObjectProphecy<FactoryInterface<PaymentInterface>> $paymentFactory */
        $paymentFactory = $this->prophesize(FactoryInterface::class);

        $this->redemptionMethod = new PaymentRedemptionMethod(
            $this->prophesize(OrderProcessorInterface::class)->reveal(),
            $this->prophesize(GiftCardCoverageCalculatorInterface::class)->reveal(),
            $this->balanceOperator->reveal(),
            $this->prophesize(GiftCardPaymentMethodProviderInterface::class)->reveal(),
            $this->paymentChecker->reveal(),
            $paymentFactory->reveal(),
            $this->stateMachine->reveal(),
            $this->giftCardRepository->reveal(),
        );
    }

    /** @test */
    public function it_restores_what_a_refunded_gift_card_payment_took_keyed_on_the_payment(): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();
        $giftCard = $this->prophesize(GiftCardInterface::class)->reveal();
        $payment = $this->giftCardPayment(42, PaymentInterface::STATE_REFUNDED, 3000, 'CARD0001', $order);

        $this->giftCardRepository->findOneByCode('CARD0001')->willReturn($giftCard);

        $this->balanceOperator->restore($giftCard, 3000, $order, $payment, 'restore:payment:42')->shouldBeCalledOnce();

        $this->redemptionMethod->rollbackPayment($payment);
    }

    /** @test */
    public function it_does_not_restore_for_a_payment_that_is_not_a_gift_card_payment(): void
    {
        $payment = $this->prophesize(PaymentInterface::class);
        $payment->getState()->willReturn(PaymentInterface::STATE_REFUNDED);
        $this->paymentChecker->isGiftCardPayment($payment)->willReturn(false);

        $this->balanceOperator->restore(Argument::cetera())->shouldNotBeCalled();

        $this->redemptionMethod->rollbackPayment($payment->reveal());
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

        $this->redemptionMethod->rollbackPayment($payment);
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

        $order->getPayments()->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$completed, $refunded, $gateway->reveal()]));

        $this->stateMachine->can($completed, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->willReturn(true);
        $this->stateMachine->can($refunded, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->willReturn(false);

        $this->stateMachine->apply($completed, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)->shouldBeCalledOnce();
        $this->stateMachine->apply($refunded, Argument::cetera())->shouldNotBeCalled();
        $this->stateMachine->apply($gateway, Argument::cetera())->shouldNotBeCalled();

        $this->balanceOperator->restore(Argument::cetera())->shouldNotBeCalled();

        $this->redemptionMethod->rollback($order->reveal());
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
}
