<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;

final class GiftCardConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_properties(): void
    {
        $giftCardConfiguration = new GiftCardConfiguration();

        $giftCardConfiguration->setCode('super-code');
        $this->assertEquals('super-code', $giftCardConfiguration->getCode());

        $giftCardConfiguration->setDefault(true);
        $this->assertTrue($giftCardConfiguration->isDefault());

        $giftCardConfiguration->setDefaultValidityPeriod('2 months');
        $this->assertEquals('2 months', $giftCardConfiguration->getDefaultValidityPeriod());
    }
}
