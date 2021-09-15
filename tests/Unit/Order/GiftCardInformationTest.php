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
}
