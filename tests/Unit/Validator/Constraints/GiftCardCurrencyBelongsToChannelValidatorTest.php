<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyBelongsToChannel;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCurrencyBelongsToChannelValidator;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardCurrencyBelongsToChannelValidator>
 */
final class GiftCardCurrencyBelongsToChannelValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_does_not_add_a_violation_when_the_currency_is_one_of_the_channels_currencies(): void
    {
        $giftCard = $this->giftCard('EUR', $this->channel('DKK', ['DKK', 'EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyBelongsToChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_accepts_the_base_currency_of_the_channel(): void
    {
        // An order can be priced in the base currency even when it is not in the channel's currency list
        $giftCard = $this->giftCard('DKK', $this->channel('DKK', ['EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyBelongsToChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_adds_a_violation_when_the_currency_is_not_available_in_the_channel(): void
    {
        $giftCard = $this->giftCard('USD', $this->channel('DKK', ['DKK', 'EUR']));

        $this->validator->validate($giftCard, new GiftCardCurrencyBelongsToChannel());

        $this->buildViolation('setono_sylius_gift_card.gift_card.currency_code.not_in_channel')
            ->setParameter('{{ currency }}', 'USD')
            ->setParameter('{{ channel }}', 'Web store')
            ->atPath('property.path.currencyCode')
            ->assertRaised();
    }

    /** @test */
    public function it_leaves_a_missing_currency_or_channel_to_the_property_constraints(): void
    {
        $withoutChannel = new GiftCard();
        $withoutChannel->setCurrencyCode('USD');

        $this->validator->validate($withoutChannel, new GiftCardCurrencyBelongsToChannel());

        $withoutCurrency = new GiftCard();
        $withoutCurrency->setChannel($this->channel('DKK', ['DKK']));

        $this->validator->validate($withoutCurrency, new GiftCardCurrencyBelongsToChannel());

        $this->validator->validate(null, new GiftCardCurrencyBelongsToChannel());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_gift_cards(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new GiftCardCurrencyBelongsToChannel());
    }

    protected function createValidator(): GiftCardCurrencyBelongsToChannelValidator
    {
        return new GiftCardCurrencyBelongsToChannelValidator();
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
