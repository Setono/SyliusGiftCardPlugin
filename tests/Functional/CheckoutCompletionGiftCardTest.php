<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Completing checkout is where the plugin's hooks meet Sylius' own. Reconcile has to give every unit its gift card
 * before Sylius resolves the order's payment state, because an order that needs no payment (a 100 % promotion
 * leaves it with a total of 0 and no payments, which Sylius' resolver treats as paid) is marked paid during that
 * same transition, and paying the order is what enables and emails the cards. Were reconcile to run later, the
 * cards it creates for the units the customer added by bumping the quantity would stay pending forever.
 *
 * The plugin's hook and Sylius' callbacks are ordered by their priorities, separately for each adapter, so the
 * checkout is completed through each
 */
final class CheckoutCompletionGiftCardTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function it_enables_a_card_for_every_unit_when_the_order_is_paid_as_checkout_completes(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        $order = $this->createFreeOrderWithTwoGiftCardUnits();

        $this->placeOrder($order);

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
            self::assertSame(5000, $giftCard->getAmount(), 'the card should carry the amount chosen for it');
        }
    }

    /**
     * What a cart looks like when checkout completes with a 100 % promotion: two units of a virtual gift card, of
     * which only the first carries the pending card that add-to-cart created (bumping the quantity adds units
     * without cards), an order level promotion adjustment bringing the total to 0 and, because of that, no
     * payments at all, which is why Sylius marks the order paid while completing it
     */
    private function createFreeOrderWithTwoGiftCardUnits(): Order
    {
        $order = $this->createCart();
        $item = $this->addItem($order, 'GIFT_CARD', 5000, giftCard: true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $unit->setGiftCard($this->createPendingGiftCard('CHECKOUT00000001'));
        new OrderItemUnit($item);

        $promotion = new Adjustment();
        $promotion->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $promotion->setLabel('100 % off');
        $promotion->setAmount(-$order->getItemsTotal());
        $order->addAdjustment($promotion);

        self::assertSame(0, $order->getTotal(), 'precondition: the promotion covers the whole order');
        self::assertTrue($order->getPayments()->isEmpty(), 'precondition: an order that costs nothing has no payments');

        $this->manager->flush();

        return $order;
    }
}
