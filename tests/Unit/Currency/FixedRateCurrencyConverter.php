<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Currency;

use Sylius\Component\Currency\Converter\CurrencyConverterInterface;

/**
 * Converts the way Sylius' converter applies an exchange rate, multiplying or dividing by the ratio and rounding to the
 * minor unit, at fixed rates: what a unit of the reference currency is worth in each currency
 */
final class FixedRateCurrencyConverter implements CurrencyConverterInterface
{
    /**
     * @param array<string, float> $rates by currency code, the reference currency's being 1
     */
    public function __construct(
        private readonly array $rates = ['USD' => 1.0, 'DKK' => 7.46],
    ) {
    }

    public function convert(int $value, string $sourceCurrencyCode, string $targetCurrencyCode): int
    {
        if ($sourceCurrencyCode === $targetCurrencyCode) {
            return $value;
        }

        return (int) round($value * $this->rates[$targetCurrencyCode] / $this->rates[$sourceCurrencyCode]);
    }
}
