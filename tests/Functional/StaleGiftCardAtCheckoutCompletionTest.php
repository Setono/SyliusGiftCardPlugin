<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Exception\UnderpaidOrderException;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Nothing re-validates a gift card between the cart and "Place order". A cart the card fully covered skipped the
 * payment step and carries no gateway payment, so if the card is spent from another cart, disabled or adjusted
 * before the customer completes checkout, commit finds nothing to redeem and creates no payment, and Sylius' payment
 * state resolver, finding no payments at all, marks the order paid: goods for free. The complete transition is
 * guarded against that on both adapters, and commit refuses to place an underpaid order should anything get past
 * the guard
 */
final class StaleGiftCardAtCheckoutCompletionTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function a_usable_gift_card_pays_the_order_at_checkout_completion(): void
    {
        $giftCard = $this->createEnabledGiftCard('CONTROL000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->manager->flush();

        $this->assertPaidByTheGiftCard($order, $giftCard);
    }

    /** @test */
    public function it_refuses_to_complete_checkout_when_an_applied_gift_card_became_unusable(): void
    {
        $giftCard = $this->createEnabledGiftCard('STALE00000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        // spent from another cart or adjusted by an admin; disabling or expiring the card has the same effect
        $giftCard->setAmount(0);
        $this->manager->flush();

        $stateMachine = $this->stateMachine();
        self::assertFalse($stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE));

        try {
            $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
            self::fail('completing the checkout should have been refused');
        } catch (StateMachineExecutionException) {
        }
        $this->manager->flush();

        $this->assertNotPlaced($order);
    }

    /** @test */
    public function it_refuses_to_complete_checkout_when_an_applied_gift_card_covers_less_than_it_did(): void
    {
        $giftCard = $this->createEnabledGiftCard('STALE00000000002', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        // still usable, but no longer enough for a cart that skipped the payment step on the strength of it
        $giftCard->setAmount(3000);
        $this->manager->flush();

        $stateMachine = $this->stateMachine();
        self::assertFalse($stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE));

        try {
            $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
            self::fail('completing the checkout should have been refused');
        } catch (StateMachineExecutionException) {
        }
        $this->manager->flush();

        $this->assertNotPlaced($order);
    }

    /**
     * Driving the transition through Symfony Workflow itself exercises the guard subscriber the way an application
     * with sylius_core.state_machine.default_adapter set to symfony_workflow would, as CheckoutCompletionGiftCardTest
     * does for the reconcile subscriber
     *
     * @test
     */
    public function symfony_workflow_completes_the_checkout_with_a_usable_gift_card(): void
    {
        $giftCard = $this->createEnabledGiftCard('WORKFLOW00000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->checkoutWorkflow()->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->manager->flush();

        $this->assertPaidByTheGiftCard($order, $giftCard);
    }

    /** @test */
    public function symfony_workflow_refuses_to_complete_checkout_when_an_applied_gift_card_became_unusable(): void
    {
        $giftCard = $this->createEnabledGiftCard('WORKFLOW00000002', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $giftCard->disable();
        $this->manager->flush();

        $workflow = $this->checkoutWorkflow();
        self::assertFalse($workflow->can($order, OrderCheckoutTransitions::TRANSITION_COMPLETE));

        try {
            $workflow->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
            self::fail('completing the checkout should have been refused');
        } catch (NotEnabledTransitionException) {
        }
        $this->manager->flush();

        $this->assertNotPlaced($order);
    }

    /** @test */
    public function commit_refuses_to_place_an_order_its_gift_cards_no_longer_pay_for(): void
    {
        $giftCard = $this->createEnabledGiftCard('COMMIT0000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $giftCard->setAmount(0);
        $this->manager->flush();

        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        $this->expectException(UnderpaidOrderException::class);

        $redemptionMethod->commit($order);
    }

    private function assertPaidByTheGiftCard(Order $order, GiftCardInterface $giftCard): void
    {
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertCount(1, $order->getPayments());

        $payment = $order->getPayments()->first();
        self::assertInstanceOf(PaymentInterface::class, $payment);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame(5000, $payment->getAmount());

        self::assertSame(0, $giftCard->getAmount());
    }

    private function assertNotPlaced(Order $order): void
    {
        self::assertSame(OrderCheckoutStates::STATE_PAYMENT_SKIPPED, $order->getCheckoutState(), 'the checkout should not have completed');
        self::assertSame(OrderInterface::STATE_CART, $order->getState());
        self::assertSame(OrderPaymentStates::STATE_CART, $order->getPaymentState(), 'the order must not be marked paid');
        self::assertCount(0, $order->getPayments());
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }

    private function checkoutWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderCheckoutTransitions::GRAPH);

        return $workflow;
    }

    private function createEnabledGiftCard(string $code, int $amount): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();
        $this->manager->persist($giftCard);

        return $giftCard;
    }

    /**
     * An ordinary product in a cart the gift card covers completely, i.e. a cart that skipped the payment step and
     * carries no gateway payment, which is where a card going stale does the most damage
     */
    private function createCheckoutReadyOrder(GiftCardInterface $giftCard): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Mug');
        $product->setSlug('mug');
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT');
        $variant->setName('Mug');
        $variant->setProduct($product);
        $variant->setShippingRequired(false);
        $this->manager->persist($variant);

        $customer = new Customer();
        $customer->setEmail('stale@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        new OrderItemUnit($item);
        $order->addItem($item);

        $order->addGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->flush();

        self::assertSame(5000, $order->getTotal(), 'precondition: the card covers the whole order');
        self::assertTrue($order->getPayments()->isEmpty(), 'precondition: a fully covered cart skipped the payment step');

        return $order;
    }
}
