<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardStatus;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\OrderInterface;

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

    /**
     * @test
     *
     * @dataProvider statuses
     */
    public function it_has_one_status_for_the_admin(GiftCardStatus $expected, bool $bought, bool $enabled, int $amount, ?string $expiresAt, bool $issued): void
    {
        $giftCard = $bought ? $this->boughtGiftCard() : new GiftCard();
        $giftCard->setEnabled($enabled);
        $giftCard->setAmount($amount);
        $giftCard->setExpiresAt(null === $expiresAt ? null : new \DateTimeImmutable($expiresAt));
        if ($issued) {
            $giftCard->addTransaction(new GiftCardTransaction());
        }

        self::assertSame($expected, $giftCard->getStatus());
        // usable is the one status a card pays with, so it must agree with the check everything else asks
        self::assertSame(GiftCardStatus::Usable === $expected, $giftCard->isUsable());
    }

    /**
     * @return iterable<string, array{GiftCardStatus, bool, bool, int, ?string, bool}> status, bought in the shop,
     *         enabled, balance, expiry, has a ledger row
     */
    public static function statuses(): iterable
    {
        yield 'issued in the admin with a balance' => [GiftCardStatus::Usable, false, true, 5000, null, true];
        yield 'bought and paid, expiring later' => [GiftCardStatus::Usable, true, true, 5000, '+1 day', true];
        yield 'past its expiry date with a balance left' => [GiftCardStatus::Expired, false, true, 5000, '-1 day', true];
        yield 'nothing left' => [GiftCardStatus::Spent, false, true, 0, null, true];
        // there was nothing left to lose when it expired, which is the more useful thing to know
        yield 'nothing left and expired since' => [GiftCardStatus::Spent, false, true, 0, '-1 day', true];
        yield 'in a cart whose order is not paid' => [GiftCardStatus::Pending, true, false, 5000, null, false];
        yield 'in a cart, expiring before it is paid' => [GiftCardStatus::Pending, true, false, 5000, '-1 day', false];
        yield 'disabled in the admin' => [GiftCardStatus::Disabled, false, false, 5000, null, true];
        // the ledger row tells a card whose order was cancelled after it was paid from one still waiting for payment
        yield 'bought, then disabled when its order was cancelled' => [GiftCardStatus::Disabled, true, false, 5000, null, true];
        yield 'disabled, spent and expired' => [GiftCardStatus::Disabled, false, false, 0, '-1 day', true];
    }

    /**
     * A bought card is only issued once its order is paid, so the card of an order cancelled before then is still
     * pending by isPending(). It waits for nothing any more, so its status says it is disabled, like the card of an
     * order cancelled after it was paid. isPending() itself keeps calling it pending: it was never issued, so it may
     * still be deleted and goes with its unit
     *
     * @test
     *
     * @dataProvider stillWaitingOrNot
     */
    public function it_is_disabled_rather_than_pending_once_its_unpaid_order_is_cancelled(string $orderState, GiftCardStatus $expected): void
    {
        $order = new Order();
        $order->setState($orderState);
        $item = new OrderItem();
        $order->addItem($item);

        $giftCard = new GiftCard();
        $giftCard->setOrderItemUnit(new OrderItemUnit($item));
        $giftCard->disable();
        $giftCard->setAmount(5000);

        self::assertTrue($giftCard->isPending());
        self::assertTrue($giftCard->isDeletable());
        self::assertSame($expected, $giftCard->getStatus());
    }

    /**
     * @return iterable<string, array{string, GiftCardStatus}>
     */
    public static function stillWaitingOrNot(): iterable
    {
        yield 'still in the cart' => [OrderInterface::STATE_CART, GiftCardStatus::Pending];
        yield 'placed and awaiting payment' => [OrderInterface::STATE_NEW, GiftCardStatus::Pending];
        yield 'cancelled before it was paid' => [OrderInterface::STATE_CANCELLED, GiftCardStatus::Disabled];
    }

    /** @test */
    public function it_judges_the_status_by_expiry_at_a_given_date(): void
    {
        $giftCard = new GiftCard();
        $giftCard->enable();
        $giftCard->setAmount(5000);
        $giftCard->setExpiresAt(new \DateTimeImmutable('2020-01-01'));

        self::assertSame(GiftCardStatus::Usable, $giftCard->getStatus(new \DateTimeImmutable('2019-06-01')));
        self::assertSame(GiftCardStatus::Expired, $giftCard->getStatus(new \DateTimeImmutable('2020-06-01')));
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
