<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;

final class GiftCardCoverageCalculator implements GiftCardCoverageCalculatorInterface
{
    public function __construct(
        private readonly EligibleTotalCalculatorInterface $eligibleTotalCalculator,
        private readonly GiftCardEligibilityCheckerInterface $eligibilityChecker,
        private readonly CurrencyConverterInterface $currencyConverter,
    ) {
    }

    public function calculate(CoreOrderInterface $order): GiftCardCoverage
    {
        $entries = [];

        if (!$order instanceof OrderInterface) {
            return new GiftCardCoverage($entries);
        }

        $orderCurrencyCode = (string) $order->getCurrencyCode();
        $remaining = $this->eligibleTotalCalculator->getEligibleTotal($order);

        foreach ($order->getGiftCards() as $giftCard) {
            $entry = ['giftCard' => $giftCard, 'amount' => 0, 'giftCardAmount' => 0];

            if ($remaining > 0 && null === $this->eligibilityChecker->getIneligibilityReason($giftCard, $order)) {
                $entry = $this->cover($giftCard, $remaining, $orderCurrencyCode);
                $remaining -= $entry['amount'];
            }

            $entries[] = $entry;
        }

        return new GiftCardCoverage($entries);
    }

    /**
     * How much of what remains to pay, in the order's currency, the card covers, and what that takes off the card, in
     * its own currency.
     *
     * A card held in another currency is judged in that currency, against what remains to pay as the customer sees it
     * there, which is how Sylius displays the order: converted from the order's currency. A card whose balance is at
     * least that covers the rest of the order, and gives exactly what the customer was shown, so a DKK 300 card always
     * covers an order shown as DKK 300, whatever converting back and forth does to the last minor unit. A smaller card
     * gives its whole balance, converted into the order's currency
     *
     * @return array{giftCard: GiftCardInterface, amount: int, giftCardAmount: int}
     */
    private function cover(GiftCardInterface $giftCard, int $remaining, string $orderCurrencyCode): array
    {
        $balance = $giftCard->getAmount();
        $giftCardCurrencyCode = (string) $giftCard->getCurrencyCode();

        if ($giftCardCurrencyCode === $orderCurrencyCode) {
            $amount = min($balance, $remaining);

            return ['giftCard' => $giftCard, 'amount' => $amount, 'giftCardAmount' => $amount];
        }

        // At least one minor unit, so a remainder too small to show in the card's currency is not paid for nothing
        $remainingInGiftCardCurrency = max(1, $this->currencyConverter->convert($remaining, $orderCurrencyCode, $giftCardCurrencyCode));
        if ($balance >= $remainingInGiftCardCurrency) {
            return ['giftCard' => $giftCard, 'amount' => $remaining, 'giftCardAmount' => $remainingInGiftCardCurrency];
        }

        $amount = min($this->currencyConverter->convert($balance, $giftCardCurrencyCode, $orderCurrencyCode), $remaining);

        // A balance too small to be worth a minor unit of the order's currency pays nothing, so it is not taken either
        return ['giftCard' => $giftCard, 'amount' => $amount, 'giftCardAmount' => 0 === $amount ? 0 : $balance];
    }
}
