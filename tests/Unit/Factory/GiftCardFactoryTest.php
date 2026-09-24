<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_creates_a_gift_card_with_a_generated_code(): void
    {
        $giftCard = $this->createFactory(null)->createNew();

        self::assertInstanceOf(GiftCard::class, $giftCard);
        self::assertSame('ABCDEFGHIJKLMNOP', $giftCard->getCode());
    }

    /** @test */
    public function it_expires_a_new_gift_card_after_the_configured_validity_period(): void
    {
        $before = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');
        $giftCard = $this->createFactory('3 years')->createNew();
        $after = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');

        $expiresAt = $giftCard->getExpiresAt();
        self::assertNotNull($expiresAt);
        // the day is read on both sides of the call, so a test running across midnight still passes
        self::assertContains($expiresAt->format('Y-m-d'), [$before, $after]);
    }

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

    /**
     * Order amounts are kept in the channel's base currency, which is the only currency a card on the channel can
     * be compared with one to one
     *
     * @test
     */
    public function it_creates_a_gift_card_for_a_channel_in_its_base_currency(): void
    {
        $currency = new Currency();
        $currency->setCode('DKK');

        $channel = new Channel();
        $channel->setBaseCurrency($currency);

        $giftCard = $this->createFactory('3 years')->createForChannel($channel);

        self::assertSame($channel, $giftCard->getChannel());
        self::assertSame('DKK', $giftCard->getCurrencyCode());
        self::assertSame('ABCDEFGHIJKLMNOP', $giftCard->getCode());
        self::assertNotNull($giftCard->getExpiresAt());
    }

    /** @test */
    public function it_leaves_the_currency_to_be_chosen_for_a_channel_without_a_base_currency(): void
    {
        $giftCard = $this->createFactory(null)->createForChannel(new Channel());

        self::assertNull($giftCard->getCurrencyCode());
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
