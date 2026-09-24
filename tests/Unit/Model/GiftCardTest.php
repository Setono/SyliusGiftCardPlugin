<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;

final class GiftCardTest extends TestCase
{
    /** @test */
    public function it_is_usable_when_enabled_not_expired_and_has_balance(): void
    {
        $giftCard = new GiftCard();
        $giftCard->enable();
        $giftCard->setAmount(1000);

        self::assertTrue($giftCard->isUsable());
    }

    /** @test */
    public function it_is_not_usable_when_disabled(): void
    {
        $giftCard = new GiftCard();
        $giftCard->disable();
        $giftCard->setAmount(1000);

        self::assertFalse($giftCard->isUsable());
    }

    /** @test */
    public function it_is_not_usable_when_balance_is_zero(): void
    {
        $giftCard = new GiftCard();
        $giftCard->enable();
        $giftCard->setAmount(0);

        self::assertFalse($giftCard->isUsable());
    }

    /** @test */
    public function it_is_not_usable_when_expired(): void
    {
        $giftCard = new GiftCard();
        $giftCard->enable();
        $giftCard->setAmount(1000);
        $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day'));

        self::assertFalse($giftCard->isUsable());
    }

    /** @test */
    public function it_reports_expiry_relative_to_a_given_date(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setExpiresAt(new \DateTimeImmutable('2020-01-01'));

        self::assertTrue($giftCard->isExpired(new \DateTimeImmutable('2020-06-01')));
        self::assertFalse($giftCard->isExpired(new \DateTimeImmutable('2019-06-01')));
    }

    /** @test */
    public function it_never_expires_without_an_expiry_date(): void
    {
        $giftCard = new GiftCard();

        self::assertFalse($giftCard->isExpired());
    }

    /**
     * A card issued in the admin never belongs to a unit, so it is not pending however it was created
     *
     * @test
     */
    public function it_is_not_pending_without_an_order_item_unit(): void
    {
        $giftCard = new GiftCard();
        $giftCard->disable();

        self::assertFalse($giftCard->isPending());
    }

    /**
     * What add to cart leaves behind: a disabled card on a unit whose order has not been paid, which is what the
     * cleanup listener may delete together with its unit
     *
     * @test
     */
    public function it_is_pending_while_disabled_on_a_unit_without_balance_movements(): void
    {
        $giftCard = $this->boughtGiftCard();
        $giftCard->disable();

        self::assertTrue($giftCard->isPending());
    }

    /** @test */
    public function it_is_not_pending_once_it_has_a_transaction(): void
    {
        $giftCard = $this->boughtGiftCard();
        $giftCard->disable();
        $giftCard->addTransaction(new GiftCardTransaction());

        self::assertFalse($giftCard->isPending());
    }

    /** @test */
    public function it_is_not_pending_once_enabled(): void
    {
        $giftCard = $this->boughtGiftCard();
        $giftCard->enable();

        self::assertFalse($giftCard->isPending());
    }

    /** @test */
    public function it_can_be_deleted_while_pending(): void
    {
        $giftCard = $this->boughtGiftCard();
        $giftCard->disable();

        self::assertTrue($giftCard->isDeletable());
    }

    /**
     * A card that was bought was paid for with real money, so it stays whatever its state
     *
     * @test
     */
    public function it_cannot_be_deleted_once_it_was_bought(): void
    {
        $giftCard = $this->boughtGiftCard();
        $giftCard->enable();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        self::assertFalse($giftCard->isDeletable());
    }

    /** @test */
    public function it_can_be_deleted_while_an_admin_issued_card_is_untouched(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        self::assertTrue($giftCard->isDeletable());
    }

    /** @test */
    public function it_cannot_be_deleted_once_an_admin_issued_card_was_spent_from(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(3000);

        self::assertFalse($giftCard->isDeletable());
    }

    /** @test */
    public function it_links_itself_to_the_unit_it_was_bought_with(): void
    {
        $unit = new OrderItemUnit(new OrderItem());
        $giftCard = new GiftCard();

        $giftCard->setOrderItemUnit($unit);

        self::assertSame($unit, $giftCard->getOrderItemUnit());
        self::assertSame($giftCard, $unit->getGiftCard());
    }

    /** @test */
    public function it_belongs_to_the_order_of_its_unit(): void
    {
        $order = new Order();
        $item = new OrderItem();
        $order->addItem($item);

        $giftCard = new GiftCard();
        self::assertNull($giftCard->getOrder(), 'a card issued in the admin belongs to no order');

        $giftCard->setOrderItemUnit(new OrderItemUnit($item));

        self::assertSame($order, $giftCard->getOrder());
    }

    /** @test */
    public function it_records_each_order_it_is_applied_to_once(): void
    {
        $order = new Order();
        $giftCard = new GiftCard();
        self::assertFalse($giftCard->hasAppliedOrders());

        $giftCard->addAppliedOrder($order);
        $giftCard->addAppliedOrder($order);

        self::assertTrue($giftCard->hasAppliedOrders());
        self::assertTrue($giftCard->hasAppliedOrder($order));
        self::assertCount(1, $giftCard->getAppliedOrders());

        $giftCard->removeAppliedOrder($order);
        $giftCard->removeAppliedOrder($order);

        self::assertFalse($giftCard->hasAppliedOrder($order));
        self::assertFalse($giftCard->hasAppliedOrders());
    }

    /** @test */
    public function it_owns_the_transactions_added_to_it_once(): void
    {
        $transaction = new GiftCardTransaction();
        $giftCard = new GiftCard();

        $giftCard->addTransaction($transaction);
        $giftCard->addTransaction($transaction);

        self::assertCount(1, $giftCard->getTransactions());
        self::assertSame($giftCard, $transaction->getGiftCard());
    }

    /**
     * The form writes a blank amount into the card before validation reports it, and everything reading the
     * balance in between must still get a number
     *
     * @test
     */
    public function it_has_no_balance_while_its_amount_is_blank(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(null);

        self::assertSame(0, $giftCard->getAmount());
    }

    /** @test */
    public function it_is_represented_by_its_code(): void
    {
        $giftCard = new GiftCard();
        self::assertSame('', (string) $giftCard);

        $giftCard->setCode('ABCDEFGHJKMNPQRS');
        self::assertSame('ABCDEFGHJKMNPQRS', (string) $giftCard);
    }

    /**
     * A card an admin creates is emailed to its customer unless the admin says otherwise
     *
     * @test
     */
    public function it_asks_for_the_notification_email_by_default(): void
    {
        self::assertTrue((new GiftCard())->getSendNotificationEmail());
    }

    private function boughtGiftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setOrderItemUnit(new OrderItemUnit(new OrderItem()));

        return $giftCard;
    }
}
