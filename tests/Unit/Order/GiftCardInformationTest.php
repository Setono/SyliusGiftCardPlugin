<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;

final class GiftCardInformationTest extends TestCase
{
    #[Test]
    public function it_has_properties(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');

        $this->assertEquals($giftCardInformation->getAmount(), 80);
        $this->assertEquals($giftCardInformation->getCustomMessage(), 'message');
    }

    #[Test]
    public function it_has_settable_amount(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');
        $giftCardInformation->setAmount(200);
        $this->assertEquals($giftCardInformation->getAmount(), 200);
    }

    #[Test]
    public function it_has_settable_message(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');
        $giftCardInformation->setCustomMessage('new message');
        $this->assertEquals($giftCardInformation->getCustomMessage(), 'new message');
    }
}
