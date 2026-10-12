<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * The result of computing how much each gift card applied to an order covers. Each entry has two amounts: `amount`,
 * what the card pays, in the order's currency, and `giftCardAmount`, what that takes off the card, in the card's
 * currency. The two are the same for a card held in the order's currency
 */
final class GiftCardCoverage
{
    /**
     * @param list<array{giftCard: GiftCardInterface, amount: int, giftCardAmount: int}> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {
    }

    /**
     * @return list<array{giftCard: GiftCardInterface, amount: int, giftCardAmount: int}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * What the gift cards pay together, in the order's currency
     */
    public function getTotal(): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            $total += $entry['amount'];
        }

        return $total;
    }

    /**
     * What the gift card pays, in the order's currency
     */
    public function getAmountForGiftCard(GiftCardInterface $giftCard): int
    {
        foreach ($this->entries as $entry) {
            if ($entry['giftCard'] === $giftCard) {
                return $entry['amount'];
            }
        }

        return 0;
    }

    /**
     * What paying takes off the gift card, in the card's currency
     */
    public function getGiftCardAmountForGiftCard(GiftCardInterface $giftCard): int
    {
        foreach ($this->entries as $entry) {
            if ($entry['giftCard'] === $giftCard) {
                return $entry['giftCardAmount'];
            }
        }

        return 0;
    }

    /**
     * What paying takes off the gift cards together, in the given currency, when every card that pays anything is held
     * in it; null when one is held in another currency, as the cards' own amounts then add up to nothing meaningful
     */
    public function getGiftCardAmountTotal(string $currencyCode): ?int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            if (0 === $entry['amount']) {
                continue;
            }

            if ($entry['giftCard']->getCurrencyCode() !== $currencyCode) {
                return null;
            }

            $total += $entry['giftCardAmount'];
        }

        return $total;
    }
}
