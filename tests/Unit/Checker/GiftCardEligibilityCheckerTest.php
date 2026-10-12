<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityChecker;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProvider;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Currency\Context\CompositeCurrencyContext;
use Sylius\Component\Currency\Model\Currency;

final class GiftCardEligibilityCheckerTest extends TestCase
{
    /** @test */
    public function it_finds_nothing_wrong_with_a_usable_gift_card_that_matches_the_order(): void
    {
        self::assertNull(self::checker()->getIneligibilityReason($this->usableGiftCard(), $this->order()));
    }

    /**
     * @dataProvider spoilers
     *
     * @param \Closure(GiftCard, Order): void $spoil
     *
     * @test
     */
    public function it_tells_why_a_gift_card_cannot_pay_for_the_order(\Closure $spoil, GiftCardIneligibilityReason $reason): void
    {
        $giftCard = $this->usableGiftCard();
        $order = $this->order();
        $spoil($giftCard, $order);

        self::assertSame($reason, self::checker()->getIneligibilityReason($giftCard, $order));
    }

    /** @test */
    public function it_judges_only_the_card_itself_without_an_order(): void
    {
        $checker = self::checker();

        $giftCard = $this->usableGiftCard();
        self::assertNull($checker->getIneligibilityReason($giftCard));

        $giftCard->disable();
        self::assertSame(GiftCardIneligibilityReason::NotEnabled, $checker->getIneligibilityReason($giftCard));
    }

    /**
     * A card held in another currency than the order's pays converted into the order's currency, as long as the
     * order's channel offers the card's currency (#488)
     *
     * @test
     */
    public function it_finds_nothing_wrong_with_a_gift_card_in_a_currency_the_channel_offers(): void
    {
        $channel = self::channel('WEB', 'USD', 'EUR');

        $giftCard = $this->usableGiftCard();
        $giftCard->setChannel($channel);
        $giftCard->setCurrencyCode('EUR');

        $order = $this->order();
        $order->setChannel($channel);

        self::assertNull(self::checker()->getIneligibilityReason($giftCard, $order));
    }

    /** @test */
    public function it_refuses_a_gift_card_in_a_currency_the_channel_does_not_offer(): void
    {
        $channel = self::channel('WEB', 'USD', 'EUR');

        $giftCard = $this->usableGiftCard();
        $giftCard->setChannel($channel);
        $giftCard->setCurrencyCode('SEK');

        $order = $this->order();
        $order->setChannel($channel);

        self::assertSame(GiftCardIneligibilityReason::CurrencyMismatch, self::checker()->getIneligibilityReason($giftCard, $order));
    }

    /**
     * @return iterable<string, array{\Closure(GiftCard, Order): void, GiftCardIneligibilityReason}>
     */
    public function spoilers(): iterable
    {
        yield 'disabled' => [
            static function (GiftCard $giftCard): void {
                $giftCard->disable();
            },
            GiftCardIneligibilityReason::NotEnabled,
        ];
        yield 'expired' => [
            static function (GiftCard $giftCard): void {
                $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day'));
            },
            GiftCardIneligibilityReason::Expired,
        ];
        yield 'spent' => [
            static function (GiftCard $giftCard): void {
                $giftCard->setAmount(0);
            },
            GiftCardIneligibilityReason::NoBalance,
        ];
        yield 'another channel' => [
            static function (GiftCard $giftCard, Order $order): void {
                $order->setChannel(self::channel('OTHER'));
            },
            GiftCardIneligibilityReason::ChannelMismatch,
        ];
        yield 'another currency' => [
            static function (GiftCard $giftCard, Order $order): void {
                $order->setCurrencyCode('EUR');
            },
            GiftCardIneligibilityReason::CurrencyMismatch,
        ];
    }

    private function usableGiftCard(): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setChannel(self::channel('WEB'));
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->enable();

        return $giftCard;
    }

    private function order(): Order
    {
        $order = new Order();
        $order->setChannel(self::channel('WEB'));
        $order->setCurrencyCode('USD');

        return $order;
    }

    private static function checker(): GiftCardEligibilityChecker
    {
        return new GiftCardEligibilityChecker(new GiftCardCurrencyProvider(new CompositeCurrencyContext()));
    }

    private static function channel(string $code, ?string $baseCurrencyCode = null, string ...$currencyCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

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
