<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_pins_the_expiry_to_the_end_of_the_day(): void
    {
        $giftCard = $this->createFactory('3 years')->createNew();

        $expiresAt = $giftCard->getExpiresAt();
        self::assertNotNull($expiresAt);
        self::assertSame('23:59:59', $expiresAt->format('H:i:s'));
    }

    /** @test */
    public function it_leaves_the_expiry_empty_when_no_validity_period_is_configured(): void
    {
        $giftCard = $this->createFactory(null)->createNew();

        self::assertNull($giftCard->getExpiresAt());
    }

    private function createFactory(?string $validityPeriod): GiftCardFactory
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->will(fn (): GiftCardInterface => new GiftCard());

        $codeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->generate()->willReturn('ABCDEFGHIJKLMNOP');

        return new GiftCardFactory($decorated->reveal(), $codeGenerator->reveal(), $validityPeriod);
    }
}
