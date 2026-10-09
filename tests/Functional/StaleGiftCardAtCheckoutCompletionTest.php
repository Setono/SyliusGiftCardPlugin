<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodDisabledException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Setono\SyliusGiftCardPlugin\Exception\UnderpaidOrderException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Abstraction\StateMachine\Exception\StateMachineExecutionException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * Nothing re-validates a gift card between the cart and "Place order". A cart the card fully covered skipped the
 * payment step and carries no gateway payment, so if the card is spent from another cart, disabled or adjusted
 * before the customer completes checkout, commit finds nothing to redeem and creates no payment, and Sylius' payment
 * state resolver, finding no payments at all, marks the order paid: goods for free. The complete transition is
 * guarded against that on both adapters, and commit refuses to place an underpaid order should anything get past
 * the guard
 */
final class StaleGiftCardAtCheckoutCompletionTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function a_usable_gift_card_pays_the_order_at_checkout_completion(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('CONTROL000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->placeOrder($order);

        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertCount(1, $order->getPayments());

        $payment = $order->getPayments()->first();
        self::assertInstanceOf(PaymentInterface::class, $payment);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame(5000, $payment->getAmount());

        self::assertSame(0, $giftCard->getAmount());
    }

    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function it_refuses_to_complete_checkout_when_an_applied_gift_card_was_spent(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('STALE00000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        // spent from another cart or adjusted by an admin
        $giftCard->setAmount(0);
        $this->manager->flush();

        $this->assertPlacingTheOrderIsRefused($order);
    }

    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function it_refuses_to_complete_checkout_when_an_applied_gift_card_was_disabled(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('STALE00000000002', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        // expiring the card has the same effect
        $giftCard->disable();
        $this->manager->flush();

        $this->assertPlacingTheOrderIsRefused($order);
    }

    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function it_refuses_to_complete_checkout_when_an_applied_gift_card_covers_less_than_it_did(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('STALE00000000003', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        // still usable, but no longer enough for a cart that skipped the payment step on the strength of it
        $giftCard->setAmount(3000);
        $this->manager->flush();

        $this->assertPlacingTheOrderIsRefused($order);
    }

    /**
     * The gift card payments are made with a payment method the shop sets up once. Without it the cards cannot pay at
     * all when the order is placed, however usable they are, so checkout does not complete
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function it_refuses_to_complete_checkout_while_the_gift_card_payment_method_is_missing(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('NOMETHOD00000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->removeGiftCardPaymentMethod();

        $this->assertPlacingTheOrderIsRefused($order);
        self::assertSame(5000, $giftCard->getAmount(), 'the card should have kept its balance');
    }

    /**
     * The checkout guard keeps an order with gift cards from being placed while the method is missing. Whatever places
     * one some other way is stopped here rather than given a payment without a method
     *
     * @test
     */
    public function commit_refuses_to_make_a_gift_card_payment_without_the_gift_card_payment_method(): void
    {
        $giftCard = $this->createEnabledGiftCard('NOMETHOD00000002', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->removeGiftCardPaymentMethod();

        $this->expectException(GiftCardPaymentMethodNotFoundException::class);

        $this->redemptionMethod()->commit($order);
    }

    /**
     * A disabled method refuses gift cards just as a missing one does (#484), so a card applied before the merchant
     * disabled it cannot pay either
     *
     * @test
     *
     * @dataProvider adapters
     */
    public function it_refuses_to_complete_checkout_while_the_gift_card_payment_method_is_disabled(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $giftCard = $this->createEnabledGiftCard('DISABLEDMETHOD01', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->giftCardPaymentMethod()->disable();
        $this->manager->flush();

        $this->assertPlacingTheOrderIsRefused($order);
        self::assertSame(5000, $giftCard->getAmount(), 'the card should have kept its balance');
    }

    /** @test */
    public function commit_refuses_to_make_a_gift_card_payment_with_a_disabled_gift_card_payment_method(): void
    {
        $giftCard = $this->createEnabledGiftCard('DISABLEDMETHOD02', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $this->giftCardPaymentMethod()->disable();
        $this->manager->flush();

        $this->expectException(GiftCardPaymentMethodDisabledException::class);

        $this->redemptionMethod()->commit($order);
    }

    /** @test */
    public function commit_refuses_to_place_an_order_its_gift_cards_no_longer_pay_for(): void
    {
        $giftCard = $this->createEnabledGiftCard('COMMIT0000000001', 5000);
        $order = $this->createCheckoutReadyOrder($giftCard);

        $giftCard->setAmount(0);
        $this->manager->flush();

        $this->expectException(UnderpaidOrderException::class);

        $this->redemptionMethod()->commit($order);
    }

    /**
     * The adapter refuses to complete the checkout, and the order is left the cart it was: not placed, not paid, and
     * without a payment
     */
    private function assertPlacingTheOrderIsRefused(Order $order): void
    {
        $stateMachine = $this->stateMachine();
        self::assertFalse(
            $stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE),
            'the checkout should not be allowed to complete',
        );

        try {
            $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
            self::fail('completing the checkout should have been refused');
        } catch (StateMachineExecutionException) {
        }
        $this->manager->flush();

        self::assertSame(OrderCheckoutStates::STATE_PAYMENT_SKIPPED, $order->getCheckoutState(), 'the checkout should not have completed');
        self::assertSame(OrderInterface::STATE_CART, $order->getState());
        self::assertSame(OrderPaymentStates::STATE_CART, $order->getPaymentState(), 'the order must not be marked paid');
        self::assertCount(0, $order->getPayments());
    }

    /**
     * As if the merchant had deleted the method, or given it another code
     */
    private function removeGiftCardPaymentMethod(): void
    {
        $this->giftCardPaymentMethod()->setCode('renamed');
        $this->manager->flush();
    }

    private function giftCardPaymentMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.payment_method');

        $paymentMethod = $repository->findOneBy([
            'code' => self::getContainer()->getParameter('setono_sylius_gift_card.redemption.payment_method_code'),
        ]);
        self::assertInstanceOf(PaymentMethodInterface::class, $paymentMethod);

        return $paymentMethod;
    }

    private function redemptionMethod(): GiftCardRedemptionMethodInterface
    {
        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        return $redemptionMethod;
    }

    /**
     * An ordinary product in a cart the gift card covers completely, i.e. a cart that skipped the payment step and
     * carries no gateway payment, which is where a card going stale does the most damage
     */
    private function createCheckoutReadyOrder(GiftCardInterface $giftCard): Order
    {
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 5000);
        $order->addGiftCard($giftCard);
        $this->manager->flush();

        self::assertSame(5000, $order->getTotal(), 'precondition: the card covers the whole order');
        self::assertTrue($order->getPayments()->isEmpty(), 'precondition: a fully covered cart skipped the payment step');

        return $order;
    }
}
