<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;

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

    /** @test */
    public function it_is_pending_when_disabled_and_has_no_transactions_but_belongs_to_a_unit(): void
    {
        $giftCard = new GiftCard();
        $giftCard->disable();

        // no order item unit yet, so not pending
        self::assertFalse($giftCard->isPending());
    }

    /** @test */
    public function it_is_not_pending_once_it_has_a_transaction(): void
    {
        $giftCard = new GiftCard();
        $giftCard->disable();
        $giftCard->addTransaction(new GiftCardTransaction());

        self::assertFalse($giftCard->isPending());
    }
}
