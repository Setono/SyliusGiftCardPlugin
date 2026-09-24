<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * When a gateway payment fails or is cancelled, Sylius provides a replacement payment sized to the full order
 * total and lets the after-checkout payment processor cut it down. By then the applied gift cards have been
 * redeemed, so their balances say nothing about the order any more; the completed gift card payments do
 */
final class ReplacementPaymentTest extends GiftCardFunctionalTestCase
{
    /**
     * @test
     *
     * @dataProvider gatewayPaymentEndingTransitions
     */
    public function replacement_gateway_payment_is_sized_from_what_an_exhausted_gift_card_already_paid(string $transition): void
    {
        $giftCard = $this->createEnabledGiftCard('PARTIAL000000001', 6000);
        $cash = $this->createCashPaymentMethod();
        $order = $this->createCheckoutReadyOrder(10000);
        $order->addGiftCard($giftCard);
        $gatewayPayment = $this->addGatewayPayment($order, $cash, 4000);
        $this->manager->flush();

        $this->completeCheckout($order);

        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_PAID, $order->getPaymentState());
        self::assertSame(0, $giftCard->getAmount());
        self::assertSame(4000, $gatewayPayment->getAmount());
        self::assertSame(PaymentInterface::STATE_NEW, $gatewayPayment->getState());

        $this->endPayment($gatewayPayment, $transition);

        $replacement = $order->getLastPayment(PaymentInterface::STATE_NEW);
        self::assertNotNull($replacement);
        self::assertNotSame($gatewayPayment, $replacement);
        self::assertSame($cash, $replacement->getMethod());
        self::assertSame(4000, $replacement->getAmount());
    }

    /**
     * @test
     *
     * @dataProvider gatewayPaymentEndingTransitions
     */
    public function replacement_gateway_payment_is_sized_from_what_a_gift_card_with_balance_left_already_paid(string $transition): void
    {
        // A gift card cannot pay for another gift card, so only the mug is eligible: the card covers 6000 of the
        // 10000 total and keeps 2000, and the gateway pays the remaining 4000
        $giftCard = $this->createEnabledGiftCard('PARTIAL000000002', 8000);
        $cash = $this->createCashPaymentMethod();
        $order = $this->createCheckoutReadyOrder(6000);
        $this->addItem($order, 'GIFT_CARD', 4000, giftCard: true);
        $order->addGiftCard($giftCard);
        $gatewayPayment = $this->addGatewayPayment($order, $cash, 4000);
        $this->manager->flush();

        $this->completeCheckout($order);

        self::assertSame(10000, $order->getTotal());
        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_PAID, $order->getPaymentState());
        self::assertSame(2000, $giftCard->getAmount());
        self::assertSame(4000, $gatewayPayment->getAmount());

        $this->endPayment($gatewayPayment, $transition);

        $replacement = $order->getLastPayment(PaymentInterface::STATE_NEW);
        self::assertNotNull($replacement);
        self::assertNotSame($gatewayPayment, $replacement);
        self::assertSame(4000, $replacement->getAmount());
    }

    private function completeCheckout(Order $order): void
    {
        $this->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->manager->flush();
    }

    /**
     * Sylius provides a replacement payment on both of the transitions that end a gateway payment: fail, when the
     * customer's card is declined, and cancel, when an admin cancels the payment from the order page
     *
     * @return iterable<string, array{string}>
     */
    public function gatewayPaymentEndingTransitions(): iterable
    {
        yield 'fail' => [PaymentTransitions::TRANSITION_FAIL];
        yield 'cancel' => [PaymentTransitions::TRANSITION_CANCEL];
    }

    private function endPayment(PaymentInterface $payment, string $transition): void
    {
        $this->stateMachine()->apply($payment, PaymentTransitions::GRAPH, $transition);
        $this->manager->flush();
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    private function createCashPaymentMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment_method');

        $paymentMethod = $factory->createWithGateway('offline');
        $paymentMethod->setCode('cash');
        $paymentMethod->setEnabled(true);
        $paymentMethod->getGatewayConfig()?->setGatewayName('cash');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Cash');
        $paymentMethod->addChannel($this->getChannel());

        $this->manager->persist($paymentMethod);

        return $paymentMethod;
    }

    /**
     * An ordinary product in a cart where the customer has picked a payment method, i.e. the state a cart is in
     * right before the customer places the order
     */
    private function createCheckoutReadyOrder(int $unitPrice): Order
    {
        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $this->addItem($order, 'MUG', $unitPrice);

        $this->manager->persist($order);

        return $order;
    }

    /**
     * The gateway payment as the checkout payment processor left it: sized to what the gift cards do not cover
     */
    private function addGatewayPayment(Order $order, PaymentMethodInterface $paymentMethod, int $amount): PaymentInterface
    {
        /** @var PaymentFactoryInterface<PaymentInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment');

        $payment = $factory->createWithAmountAndCurrencyCode($amount, 'USD');
        self::assertInstanceOf(PaymentInterface::class, $payment);
        $payment->setMethod($paymentMethod);

        $order->addPayment($payment);

        return $payment;
    }
}
