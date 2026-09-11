<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;

final class GiftCardCoverageCalculator implements GiftCardCoverageCalculatorInterface
{
    public function __construct(
        private readonly EligibleTotalCalculatorInterface $eligibleTotalCalculator,
    ) {
    }

    public function calculate(CoreOrderInterface $order): GiftCardCoverage
    {
        $entries = [];

        if (!$order instanceof OrderInterface) {
            return new GiftCardCoverage($entries);
        }

        $remaining = $this->eligibleTotalCalculator->getEligibleTotal($order);

        foreach ($order->getGiftCards() as $giftCard) {
            $amount = 0;

            if ($remaining > 0 && $this->isEligible($giftCard, $order)) {
                $amount = min($giftCard->getAmount(), $remaining);
                $remaining -= $amount;
            }

            $entries[] = ['giftCard' => $giftCard, 'amount' => $amount];
        }

        return new GiftCardCoverage($entries);
    }

    private function isEligible(GiftCardInterface $giftCard, OrderInterface $order): bool
    {
        if (!$giftCard->isUsable()) {
            return false;
        }

        return $giftCard->getCurrencyCode() === $order->getCurrencyCode();
    }
}
