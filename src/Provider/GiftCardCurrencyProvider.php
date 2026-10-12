<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

final class GiftCardCurrencyProvider implements GiftCardCurrencyProviderInterface
{
    public function __construct(
        private readonly CurrencyContextInterface $currencyContext,
    ) {
    }

    public function getCurrencyCodes(ChannelInterface $channel): array
    {
        $currencyCodes = [];

        $baseCurrencyCode = $channel->getBaseCurrency()?->getCode();
        if (null !== $baseCurrencyCode) {
            $currencyCodes[] = $baseCurrencyCode;
        }

        foreach ($channel->getCurrencies() as $currency) {
            $currencyCode = $currency->getCode();
            if (null !== $currencyCode && !in_array($currencyCode, $currencyCodes, true)) {
                $currencyCodes[] = $currencyCode;
            }
        }

        return $currencyCodes;
    }

    public function getCurrencyCode(ChannelInterface $channel): string
    {
        $baseCurrencyCode = (string) $channel->getBaseCurrency()?->getCode();

        try {
            $currencyCode = $this->currencyContext->getCurrencyCode();
        } catch (\RuntimeException) {
            // Outside a shop request (a console command, the admin) nobody is browsing in any currency. Sylius' currency
            // context then finds no currency, or no channel to read one for, and says so with a runtime exception of
            // either kind (CurrencyNotFoundException, ChannelNotFoundException)
            return $baseCurrencyCode;
        }

        return in_array($currencyCode, $this->getCurrencyCodes($channel), true) ? $currencyCode : $baseCurrencyCode;
    }
}
