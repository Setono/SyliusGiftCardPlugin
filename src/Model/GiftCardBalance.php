<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

final class GiftCardBalance
{
    /** @var string */
    private $currencyCode;

    /** @var int */
    private $count = 0;

    /** @var int */
    private $total = 0;

    public function __construct(string $currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    public function add(int $amount): void
    {
        ++$this->count;
        $this->total += $amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getAverageAmount(): int
    {
        return (int) round($this->total / $this->count);
    }
}
