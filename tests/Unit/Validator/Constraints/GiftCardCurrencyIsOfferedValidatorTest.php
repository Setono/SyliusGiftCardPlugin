<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsOffered;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsOfferedValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The add to cart form submits the currency its amount field was shown in (#488), which has to be one the channel being
 * shopped in sells gift cards in
 *
 * @extends ConstraintValidatorTestCase<GiftCardCurrencyIsOfferedValidator>
 */
final class GiftCardCurrencyIsOfferedValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /**
     * @test
     *
     * @dataProvider offeredCurrencies
     */
    public function it_accepts_a_currency_the_channel_offers(string $currencyCode): void
    {
        $this->validator->validate($currencyCode, new GiftCardCurrencyIsOffered());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function offeredCurrencies(): iterable
    {
        yield 'the base currency' => ['USD'];
        yield 'another currency of the channel' => ['EUR'];
    }

    /**
     * The message does not repeat the value, which a request can make anything, markup included, and Sylius' add to
     * cart script inserts the message into the page as markup
     *
     * @test
     */
    public function it_rejects_a_currency_the_channel_does_not_offer_without_repeating_it(): void
    {
        $this->validator->validate('<img src=x onerror=alert(1)>', new GiftCardCurrencyIsOffered());

        $this->buildViolation('setono_sylius_gift_card.gift_card_information.currency_code.not_offered')
            ->assertRaised();
    }

    /**
     * A submission that names no currency is in the one the customer browses in now
     *
     * @test
     */
    public function it_accepts_no_currency(): void
    {
        $this->validator->validate(null, new GiftCardCurrencyIsOffered());

        $this->assertNoViolation();
    }

    /**
     * Outside a shop request there is no channel to hold the currency to
     *
     * @test
     */
    public function it_has_nothing_to_hold_the_currency_to_without_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willThrow(new ChannelNotFoundException());
        $this->validator->validate('SEK', new GiftCardCurrencyIsOffered());

        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());
        $this->validator->validate('SEK', new GiftCardCurrencyIsOffered());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_currency_codes(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(978, new GiftCardCurrencyIsOffered());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('USD', new NotBlank());
    }

    protected function createValidator(): GiftCardCurrencyIsOfferedValidator
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($channel);

        $currencyProvider = $this->prophesize(GiftCardCurrencyProviderInterface::class);
        $currencyProvider->getCurrencyCodes($channel)->willReturn(['USD', 'EUR']);

        return new GiftCardCurrencyIsOfferedValidator($this->channelContext->reveal(), $currencyProvider->reveal());
    }
}
