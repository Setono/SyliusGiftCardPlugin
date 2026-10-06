<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface as CoreOrderItemInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class GiftCardInformationFactory implements GiftCardInformationFactoryInterface
{
    /**
     * @param class-string<GiftCardInformationInterface> $className
     */
    public function __construct(
        private readonly string $className,
        private readonly ProductVariantPricesCalculatorInterface $productVariantPricesCalculator,
        private readonly GiftCardAmountLimitsProviderInterface $amountLimitsProvider,
    ) {
    }

    public function createNew(OrderInterface $cart, OrderItemInterface $cartItem): GiftCardInformationInterface
    {
        return new $this->className($this->resolveInitialAmount($cart, $cartItem));
    }

    /**
     * The amount field starts out at the price the product page shows next to it, so the customer adjusts a suggested
     * amount rather than filling in a blank. That is the variant's price in the cart's channel, not the line's unit
     * price: Sylius only prices a line once it has been added to the cart, so on the product page the line costs 0.
     * The channel price is in the channel's base currency, which is the currency the amount field is in.
     *
     * Without a variant or a channel there is nothing to suggest. A shop whose maximum equals its minimum sells gift
     * cards of that one amount, so the field starts out at it, whatever the product is priced at. Otherwise, without a
     * price for the variant in the channel there is nothing to suggest either, and a price the shop refuses as an
     * amount, because the product is sold for nothing or for less or more than the purchase limits allow, would start
     * the customer off at an error. In each of those cases the field starts out empty
     */
    private function resolveInitialAmount(OrderInterface $cart, OrderItemInterface $cartItem): ?int
    {
        $channel = $cart instanceof CoreOrderInterface ? $cart->getChannel() : null;
        $variant = $cartItem instanceof CoreOrderItemInterface ? $cartItem->getVariant() : null;

        if (!$channel instanceof ChannelInterface || null === $variant) {
            return null;
        }

        $limits = $this->amountLimitsProvider->getLimits($channel);
        if ($limits->maximum === $limits->minimum) {
            return $limits->minimum;
        }

        try {
            $price = $this->productVariantPricesCalculator->calculate($variant, ['channel' => $channel]);
        } catch (MissingChannelConfigurationException) {
            return null;
        }

        if ($price <= 0 || $price < $limits->minimum || (null !== $limits->maximum && $price > $limits->maximum)) {
            return null;
        }

        return $price;
    }
}
