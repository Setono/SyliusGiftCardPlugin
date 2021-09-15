<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;

final class GiftCardInformationTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_properties(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');

        $this->assertEquals($giftCardInformation->getAmount(), 80);
        $this->assertEquals($giftCardInformation->getCustomMessage(), 'message');
    }

    /**
     * @test
     */
    public function it_has_settable_amount(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');
        $giftCardInformation->setAmount(200);
        $this->assertEquals($giftCardInformation->getAmount(), 200);
    }

    /**
     * @test
     */
    public function it_has_settable_message(): void
    {
        $giftCardInformation = new GiftCardInformation(80, 'message');
        $giftCardInformation->setCustomMessage('new message');
        $this->assertEquals($giftCardInformation->getCustomMessage(), 'new message');
    }
}
