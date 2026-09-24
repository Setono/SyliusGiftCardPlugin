<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsChannelBaseCurrency;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyIsChannelBaseCurrencyValidator;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardCurrencyIsChannelBaseCurrencyValidator>
 */
final class GiftCardCurrencyIsChannelBaseCurrencyValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_rejects_a_channel_currency_that_is_not_the_base_currency(): void
    {
        // EUR is offered for display, but every order amount and gift card balance is in DKK
        $giftCard = $this->giftCard('EUR', $this->channel('DKK', ['DKK', 'EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyIsChannelBaseCurrency());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_base_currency')
            ->setParameter('{{ currency }}', 'EUR')
            ->setParameter('{{ base_currency }}', 'DKK')
            ->setParameter('{{ channel }}', 'Web store')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /** @test */
    public function it_accepts_the_base_currency_of_the_channel(): void
    {
        $giftCard = $this->giftCard('DKK', $this->channel('DKK', ['EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyIsChannelBaseCurrency());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_rejects_a_currency_the_channel_does_not_know(): void
    {
        $giftCard = $this->giftCard('USD', $this->channel('DKK', ['DKK', 'EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyIsChannelBaseCurrency());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_base_currency')
            ->setParameter('{{ currency }}', 'USD')
            ->setParameter('{{ base_currency }}', 'DKK')
            ->setParameter('{{ channel }}', 'Web store')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /** @test */
    public function it_leaves_a_missing_currency_or_channel_to_the_property_constraints(): void
    {
        $withoutChannel = new GiftCard();
        $withoutChannel->setCurrencyCode('USD');

        $this->validator->validate($withoutChannel, new GiftCardCurrencyIsChannelBaseCurrency());

        $withoutCurrency = new GiftCard();
        $withoutCurrency->setChannel($this->channel('DKK', ['DKK']));

        $this->validator->validate($withoutCurrency, new GiftCardCurrencyIsChannelBaseCurrency());

        $this->validator->validate(null, new GiftCardCurrencyIsChannelBaseCurrency());

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

        $this->validator->validate($this->giftCard('EUR', $channel->reveal()), new GiftCardCurrencyIsChannelBaseCurrency());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_base_currency')
            ->setParameter('{{ currency }}', 'EUR')
            ->setParameter('{{ base_currency }}', 'DKK')
            ->setParameter('{{ channel }}', 'WEB')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /**
     * A channel without a base currency is a misconfigured channel, not a gift card in the wrong currency
     *
     * @test
     */
    public function it_has_nothing_to_compare_against_in_a_channel_without_a_base_currency(): void
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getBaseCurrency()->willReturn(null);

        $this->validator->validate($this->giftCard('EUR', $channel->reveal()), new GiftCardCurrencyIsChannelBaseCurrency());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_leaves_a_blank_currency_to_the_property_constraints(): void
    {
        $this->validator->validate($this->giftCard('', $this->channel('DKK', ['DKK'])), new GiftCardCurrencyIsChannelBaseCurrency());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_gift_cards(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new GiftCardCurrencyIsChannelBaseCurrency());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new GiftCard(), new NotBlank());
    }

    protected function createValidator(): GiftCardCurrencyIsChannelBaseCurrencyValidator
    {
        return new GiftCardCurrencyIsChannelBaseCurrencyValidator();
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
