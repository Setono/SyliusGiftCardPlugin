<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardExpiryResolverInterface;
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

    /**
     * The expiry is counted from now, which is final for a card issued in the admin. A card bought in the shop gets
     * its final expiry when the order is placed
     *
     * @test
     */
    public function it_gives_a_new_gift_card_the_expiry_resolved_for_a_card_issued_now(): void
    {
        $expiresAt = new \DateTimeImmutable('2029-09-25 23:59:59');

        $giftCard = $this->createFactory($expiresAt)->createNew();

        self::assertSame($expiresAt, $giftCard->getExpiresAt());
    }

    /** @test */
    public function it_leaves_the_expiry_empty_when_gift_cards_never_expire(): void
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

        $expiresAt = new \DateTimeImmutable('2029-09-25 23:59:59');

        $giftCard = $this->createFactory($expiresAt)->createForChannel($channel);

        self::assertSame($channel, $giftCard->getChannel());
        self::assertSame('DKK', $giftCard->getCurrencyCode());
        self::assertSame('ABCDEFGHIJKLMNOP', $giftCard->getCode());
        self::assertSame($expiresAt, $giftCard->getExpiresAt());
    }

    /** @test */
    public function it_leaves_the_currency_to_be_chosen_for_a_channel_without_a_base_currency(): void
    {
        $giftCard = $this->createFactory(null)->createForChannel(new Channel());

        self::assertNull($giftCard->getCurrencyCode());
    }

    private function createFactory(?\DateTimeImmutable $expiresAt): GiftCardFactory
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->will(fn (): GiftCardInterface => new GiftCard());

        $codeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $codeGenerator->generate()->willReturn('ABCDEFGHIJKLMNOP');

        $expiryResolver = $this->prophesize(GiftCardExpiryResolverInterface::class);
        $expiryResolver->resolve()->willReturn($expiresAt);

        return new GiftCardFactory($decorated->reveal(), $codeGenerator->reveal(), $expiryResolver->reveal());
    }
}
