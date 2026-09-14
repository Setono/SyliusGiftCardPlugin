<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Completing checkout is where the plugin's hooks meet Sylius' own. Reconcile has to give every unit its gift card
 * before Sylius resolves the order's payment state, because an order that needs no payment (a 100 % promotion
 * leaves it with a total of 0 and no payments, which Sylius' resolver treats as paid) is marked paid during that
 * same transition, and paying the order is what enables and emails the cards. Were reconcile to run later, the
 * cards it creates for the units the customer added by bumping the quantity would stay pending forever
 */
final class CheckoutCompletionGiftCardTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_enables_a_card_for_every_unit_when_the_order_is_paid_as_checkout_completes(): void
    {
        $order = $this->createFreeOrderWithTwoGiftCardUnits('CHECKOUTWINZOU01');

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->assertEveryUnitCarriesAnEnabledGiftCard($order);
    }

    /**
     * Driving the transition through Symfony Workflow itself pits the workflow subscriber's priority against
     * Sylius' workflow listeners, the way SymfonyWorkflowGiftCardTest does for the pay transition. The transitions
     * Sylius cascades from there (create, pay) still go through the application's default adapter
     *
     * @test
     */
    public function it_enables_a_card_for_every_unit_when_symfony_workflow_completes_the_checkout(): void
    {
        $order = $this->createFreeOrderWithTwoGiftCardUnits('CHECKOUTWORKFLOW');

        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderCheckoutTransitions::GRAPH);
        $workflow->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->assertEveryUnitCarriesAnEnabledGiftCard($order);
    }

    private function assertEveryUnitCarriesAnEnabledGiftCard(Order $order): void
    {
        self::assertSame(
            OrderPaymentStates::STATE_PAID,
            $order->getPaymentState(),
            'precondition: completing checkout should have paid an order that costs nothing',
        );

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);
        self::assertCount(2, $item->getUnits());

        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            self::assertInstanceOf(GiftCardInterface::class, $giftCard, 'reconcile should have given every unit a gift card');
            self::assertTrue(
                $giftCard->isEnabled(),
                sprintf('gift card %s should have been enabled when the order was paid', (string) $giftCard->getCode()),
            );
            self::assertSame(5000, $giftCard->getAmount(), 'the card should carry the unit total it was reconciled with');
        }
    }

    /**
     * What a cart looks like when checkout completes with a 100 % promotion: two units of a gift card product, of
     * which only the first carries the pending card that add-to-cart created (bumping the quantity adds units
     * without cards), an order level promotion adjustment bringing the total to 0 and, because of that, no
     * payments at all, which is why Sylius marks the order paid while completing it
     */
    private function createFreeOrderWithTwoGiftCardUnits(string $code): Order
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
        // a virtual gift card, delivered by email
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
        // complete transitions from payment_selected or payment_skipped
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        (new OrderItemUnit($item))->setGiftCard($this->createPendingGiftCard($code));
        new OrderItemUnit($item);

        $promotion = new Adjustment();
        $promotion->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $promotion->setLabel('100 % off');
        $promotion->setAmount(-$order->getItemsTotal());
        $order->addAdjustment($promotion);

        self::assertSame(0, $order->getTotal(), 'precondition: the promotion covers the whole order');
        self::assertTrue($order->getPayments()->isEmpty(), 'precondition: an order that costs nothing has no payments');

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function createPendingGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode($code);
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->disable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }
}
