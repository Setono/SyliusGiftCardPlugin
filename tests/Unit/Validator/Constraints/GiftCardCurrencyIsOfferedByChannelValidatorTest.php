<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProvider;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsOfferedByChannel;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsOfferedByChannelValidator;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Context\CompositeCurrencyContext;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * A gift card is held in a currency its channel offers, its base currency or another, and pays converted into the
 * order's currency at the exchange rate the shop keeps (#488). A card in a currency its channel does not offer could
 * never be redeemed
 *
 * @extends ConstraintValidatorTestCase<GiftCardCurrencyIsOfferedByChannelValidator>
 */
final class GiftCardCurrencyIsOfferedByChannelValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * @dataProvider offeredCurrencies
     */
    public function it_accepts_a_currency_the_channel_offers(string $currencyCode): void
    {
        // the web store is kept in kroner, and also shows its prices in euros
        $giftCard = $this->giftCard($currencyCode, $this->channel('DKK', ['EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyIsOfferedByChannel());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function offeredCurrencies(): iterable
    {
        yield 'the base currency' => ['DKK'];
        yield 'another currency of the channel' => ['EUR'];
    }

    /** @test */
    public function it_rejects_a_currency_the_channel_does_not_offer(): void
    {
        $giftCard = $this->giftCard('USD', $this->channel('DKK', ['DKK', 'EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyIsOfferedByChannel());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_offered_by_channel')
            ->setParameter('{{ currency }}', 'USD')
            ->setParameter('{{ currencies }}', 'DKK, EUR')
            ->setParameter('{{ channel }}', 'Web store')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /** @test */
    public function it_leaves_a_missing_currency_or_channel_to_the_property_constraints(): void
    {
        $withoutChannel = new GiftCard();
        $withoutChannel->setCurrencyCode('USD');

        $this->validator->validate($withoutChannel, new GiftCardCurrencyIsOfferedByChannel());

        $withoutCurrency = new GiftCard();
        $withoutCurrency->setChannel($this->channel('DKK', ['DKK']));

        $this->validator->validate($withoutCurrency, new GiftCardCurrencyIsOfferedByChannel());

        $this->validator->validate(null, new GiftCardCurrencyIsOfferedByChannel());

        $this->assertNoViolation();
    }

    /**
     * The admin reads which channel the card has to match; a channel without a name is named by its code
     *
     * @test
     */
    public function it_names_a_channel_without_a_name_by_its_code(): void
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getName()->willReturn(null);
        $channel->getCode()->willReturn('WEB');
        $channel->getBaseCurrency()->willReturn($this->currency('DKK'));
        $channel->getCurrencies()->willReturn(new ArrayCollection());

        $this->validator->validate($this->giftCard('EUR', $channel->reveal()), new GiftCardCurrencyIsOfferedByChannel());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_offered_by_channel')
            ->setParameter('{{ currency }}', 'EUR')
            ->setParameter('{{ currencies }}', 'DKK')
            ->setParameter('{{ channel }}', 'WEB')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /**
     * A channel without any currency is a misconfigured channel, not a gift card in the wrong currency
     *
     * @test
     */
    public function it_has_nothing_to_compare_against_in_a_channel_without_a_currency(): void
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getBaseCurrency()->willReturn(null);
        $channel->getCurrencies()->willReturn(new ArrayCollection());

        $this->validator->validate($this->giftCard('EUR', $channel->reveal()), new GiftCardCurrencyIsOfferedByChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_leaves_a_blank_currency_to_the_property_constraints(): void
    {
        $this->validator->validate($this->giftCard('', $this->channel('DKK', ['DKK'])), new GiftCardCurrencyIsOfferedByChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_gift_cards(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new GiftCardCurrencyIsOfferedByChannel());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new GiftCard(), new NotBlank());
    }

    protected function createValidator(): GiftCardCurrencyIsOfferedByChannelValidator
    {
        return new GiftCardCurrencyIsOfferedByChannelValidator(new GiftCardCurrencyProvider(new CompositeCurrencyContext()));
    }

    private function giftCard(string $currencyCode, ChannelInterface $channel): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCurrencyCode($currencyCode);
        $giftCard->setChannel($channel);

        return $giftCard;
    }

    /**
     * @param list<string> $currencyCodes
     */
    private function channel(string $baseCurrencyCode, array $currencyCodes): ChannelInterface
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getName()->willReturn('Web store');
        $channel->getCode()->willReturn('WEB');
        $channel->getBaseCurrency()->willReturn($this->currency($baseCurrencyCode));
        $channel->getCurrencies()->willReturn(new ArrayCollection(array_map(
            $this->currency(...),
            $currencyCodes,
        )));

        return $channel->reveal();
    }

    private function currency(string $code): CurrencyInterface
    {
        $currency = new Currency();
        $currency->setCode($code);

        return $currency;
    }
}
