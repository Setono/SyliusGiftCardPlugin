<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProvider;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Context\CurrencyNotFoundException;
use Sylius\Component\Currency\Model\Currency;

/**
 * A gift card is held in the currency it was bought or issued in, which may be any the channel offers (#488)
 */
final class GiftCardCurrencyProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The base currency comes first, as the one the admin's form starts a card out at, and a channel listing its base
     * currency among its currencies, as Sylius' channel form has it do, does not offer it twice
     *
     * @test
     */
    public function it_offers_the_base_currency_first_then_the_other_currencies_of_the_channel(): void
    {
        $provider = new GiftCardCurrencyProvider($this->prophesize(CurrencyContextInterface::class)->reveal());

        self::assertSame(['DKK', 'EUR', 'SEK'], $provider->getCurrencyCodes(self::channel('DKK', 'EUR', 'DKK', 'SEK')));
        self::assertSame(['EUR'], $provider->getCurrencyCodes(self::channel(null, 'EUR')));
        self::assertSame([], $provider->getCurrencyCodes(new Channel()));
    }

    /** @test */
    public function it_holds_a_card_bought_now_in_the_currency_the_customer_browses_in(): void
    {
        $currencyContext = $this->prophesize(CurrencyContextInterface::class);
        $currencyContext->getCurrencyCode()->willReturn('EUR');

        self::assertSame('EUR', (new GiftCardCurrencyProvider($currencyContext->reveal()))->getCurrencyCode(self::channel('DKK', 'EUR')));
    }

    /**
     * Sylius' currency context offers the customer only the channel's currencies, but a currency remembered from another
     * channel, or a context of the application's, may name one this channel does not offer, which no card can be held in
     *
     * @test
     */
    public function it_holds_a_card_bought_now_in_the_base_currency_when_the_channel_does_not_offer_the_one_browsed_in(): void
    {
        $currencyContext = $this->prophesize(CurrencyContextInterface::class);
        $currencyContext->getCurrencyCode()->willReturn('USD');

        self::assertSame('DKK', (new GiftCardCurrencyProvider($currencyContext->reveal()))->getCurrencyCode(self::channel('DKK', 'EUR')));
    }

    /**
     * Outside a shop request nobody browses in any currency: Sylius' context finds none, or no channel to read one for
     *
     * @test
     */
    public function it_holds_a_card_in_the_base_currency_when_nobody_browses_in_any_currency(): void
    {
        foreach ([new CurrencyNotFoundException(), new ChannelNotFoundException()] as $exception) {
            $currencyContext = $this->prophesize(CurrencyContextInterface::class);
            $currencyContext->getCurrencyCode()->willThrow($exception);

            self::assertSame('DKK', (new GiftCardCurrencyProvider($currencyContext->reveal()))->getCurrencyCode(self::channel('DKK', 'EUR')));
        }

        // a channel without a base currency has no currency to fall back to
        $currencyContext = $this->prophesize(CurrencyContextInterface::class);
        $currencyContext->getCurrencyCode()->willThrow(new CurrencyNotFoundException());

        self::assertSame('', (new GiftCardCurrencyProvider($currencyContext->reveal()))->getCurrencyCode(new Channel()));
    }

    private static function channel(?string $baseCurrencyCode, string ...$currencyCodes): Channel
    {
        $channel = new Channel();

        if (null !== $baseCurrencyCode) {
            $channel->setBaseCurrency(self::currency($baseCurrencyCode));
        }

        foreach ($currencyCodes as $currencyCode) {
            $channel->addCurrency(self::currency($currencyCode));
        }

        return $channel;
    }

    private static function currency(string $code): Currency
    {
        $currency = new Currency();
        $currency->setCode($code);

        return $currency;
    }
}
