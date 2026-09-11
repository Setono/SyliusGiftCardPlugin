<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * The result of computing how much each gift card applied to an order covers
 */
final class GiftCardCoverage
{
    /**
     * @param list<array{giftCard: GiftCardInterface, amount: int}> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {
    }

    /**
     * @return list<array{giftCard: GiftCardInterface, amount: int}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getTotal(): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            $total += $entry['amount'];
        }

        return $total;
    }

    public function getAmountForGiftCard(GiftCardInterface $giftCard): int
    {
        foreach ($this->entries as $entry) {
            if ($entry['giftCard'] === $giftCard) {
                return $entry['amount'];
            }
        }

        return 0;
    }
}
