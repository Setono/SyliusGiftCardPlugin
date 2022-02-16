<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfPathGenerator;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

final class GiftCardPdfPathGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_generates_gift_card_pdf_path(): void
    {
        $giftCardChannelConfiguration = $this->prophesize(GiftCardConfigurationInterface::class);
        $giftCardChannelConfiguration->getId()->willReturn(10);

        $giftCardPdfPathGenerator = new GiftCardPdfPathGenerator();
        $path = $giftCardPdfPathGenerator->generatePath($giftCardChannelConfiguration->reveal());

        $this->assertEquals('gift_card_configuration_pdf_10.pdf', $path);
    }
}
