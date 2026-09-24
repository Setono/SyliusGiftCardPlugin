<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Refunding an order that bought gift cards gives the customer their money back, so the cards must not stay
 * spendable on top of that. Only a refund of the whole order says that much, though: a partial refund does not say
 * which payments or items the money went back for, so the cards are left alone and the merchant disables them by
 * hand where that is what the refund meant
 */
final class PurchaseOrderRefundTest extends GiftCardFunctionalTestCase
{
    /**
     * The admin's "Refund" button refunds a payment, and Sylius resolves the order's payment state from that, so
     * this is the whole chain from the refunded payment down to the disabled card
     *
     * @test
     */
    public function refunding_the_payment_of_an_order_that_bought_gift_cards_disables_them(): void
    {
        $giftCard = $this->createEnabledGiftCard('PURCHASE00000001', 5000);
        $order = $this->createPaidOrderThatBought($giftCard, 'PURCHASE00000001');

        $payment = $order->getPayments()->first();
        self::assertInstanceOf(PaymentInterface::class, $payment);

        $this->stateMachine()->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        $this->manager->flush();

        self::assertSame(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState(), 'precondition: refunding the only payment refunds the order');
        self::assertFalse($giftCard->isEnabled(), 'the card bought with money that went back must not be spendable');
    }

    /** @test */
    public function a_partial_refund_leaves_the_gift_cards_the_order_bought_usable(): void
    {
        $giftCard = $this->createEnabledGiftCard('PURCHASE00000002', 5000);
        $order = $this->createPaidOrderThatBought($giftCard, 'PURCHASE00000002');

        $this->stateMachine()->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_REFUND);
        $this->manager->flush();

        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertTrue($giftCard->isEnabled(), 'a partial refund does not say what it was for, so the card stays usable');
    }

    /**
     * Driving the transition through Symfony Workflow itself exercises the workflow subscriber the way an application
     * with sylius_core.state_machine.default_adapter set to symfony_workflow would
     *
     * @test
     */
    public function symfony_workflow_refunding_an_order_that_bought_gift_cards_disables_them(): void
    {
        $giftCard = $this->createEnabledGiftCard('PURCHASE00000003', 5000);
        $order = $this->createPaidOrderThatBought($giftCard, 'PURCHASE00000003');

        $this->orderPaymentWorkflow()->apply($order, OrderPaymentTransitions::TRANSITION_REFUND);
        $this->manager->flush();

        self::assertSame(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertFalse($giftCard->isEnabled(), 'the card bought with money that went back must not be spendable');
    }

    /** @test */
    public function symfony_workflow_partially_refunding_an_order_leaves_the_gift_cards_it_bought_usable(): void
    {
        $giftCard = $this->createEnabledGiftCard('PURCHASE00000004', 5000);
        $order = $this->createPaidOrderThatBought($giftCard, 'PURCHASE00000004');

        $this->orderPaymentWorkflow()->apply($order, OrderPaymentTransitions::TRANSITION_PARTIALLY_REFUND);
        $this->manager->flush();

        self::assertSame(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertTrue($giftCard->isEnabled(), 'a partial refund does not say what it was for, so the card stays usable');
    }

    /**
     * What an order looks like once it has bought a gift card and been paid: a unit of a gift card product carrying
     * the (by now enabled) card, and one completed gateway payment for the whole order
     */
    private function createPaidOrderThatBought(GiftCardInterface $giftCard, string $code): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD_' . $code);
        $product->setName('Gift card');
        $product->setSlug('gift-card-' . strtolower($code));
        $product->setGiftCard(true);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_VARIANT_' . $code);
        $variant->setProduct($product);
        $variant->setShippingRequired(false);
        $this->manager->persist($variant);

        $customer = new Customer();
        $customer->setEmail(strtolower($code) . '@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        $order->setState(BaseOrderInterface::STATE_NEW);
        // refund and partially_refund transition from paid
        $order->setPaymentState(OrderPaymentStates::STATE_PAID);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        (new OrderItemUnit($item))->setGiftCard($giftCard);
        $order->addItem($item);

        $payment = new Payment();
        $payment->setCurrencyCode('USD');
        $payment->setAmount(5000);
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $order->addPayment($payment);

        $this->manager->persist($order);
        $this->manager->flush();

        self::assertSame(5000, $order->getTotal());

        return $order;
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    private function orderPaymentWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderPaymentTransitions::GRAPH);

        return $workflow;
    }
}
