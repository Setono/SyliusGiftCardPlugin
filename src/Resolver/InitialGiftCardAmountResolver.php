<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;

/**
 * The amount field starts out at the price the shop shows for the product, so the customer adjusts a suggested amount
 * rather than filling in a blank. That is the variant's price in the channel, not a cart line's unit price: Sylius only
 * prices a line once it has been added to the cart, so on the product page the line costs 0. The channel price is in
 * the channel's base currency, so it is converted into the currency the amount field is in, the way the shop shows its
 * prices in the currency the customer browses in.
 *
 * Without a variant there is nothing to suggest. A shop whose maximum equals its minimum sells gift cards of that one
 * amount, so the field starts out at it, whatever the product is priced at. Otherwise, without a price for the variant in
 * the channel there is nothing to suggest either, and a price the shop refuses as an amount, because the product is sold
 * for nothing or for less or more than the purchase limits allow, would start the customer off at an error. In each of
 * those cases the field starts out empty
 */
final class InitialGiftCardAmountResolver implements InitialGiftCardAmountResolverInterface
{
    public function __construct(
        private readonly ProductVariantPricesCalculatorInterface $productVariantPricesCalculator,
        private readonly GiftCardAmountLimitsProviderInterface $amountLimitsProvider,
        private readonly CurrencyConverterInterface $currencyConverter,
    ) {
    }

    public function resolve(ChannelInterface $channel, string $currencyCode, ?ProductVariantInterface $variant): ?int
    {
        if (null === $variant) {
            return null;
        }

        $limits = $this->amountLimitsProvider->getLimits($channel, $currencyCode);
        if ($limits->maximum === $limits->minimum) {
            return $limits->minimum;
        }

        try {
            $price = $this->productVariantPricesCalculator->calculate($variant, ['channel' => $channel]);
        } catch (MissingChannelConfigurationException) {
            return null;
        }

        $price = $this->currencyConverter->convert($price, (string) $channel->getBaseCurrency()?->getCode(), $currencyCode);

        if ($price <= 0 || $price < $limits->minimum || (null !== $limits->maximum && $price > $limits->maximum)) {
            return null;
        }

        return $price;
    }
}
