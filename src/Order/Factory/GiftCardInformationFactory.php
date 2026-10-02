<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
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
     * Without a variant, a channel or a price for the variant in the channel there is nothing to suggest, and a
     * product sold for nothing suggests an amount the shop refuses. The field then starts out empty
     */
    private function resolveInitialAmount(OrderInterface $cart, OrderItemInterface $cartItem): ?int
    {
        $channel = $cart instanceof CoreOrderInterface ? $cart->getChannel() : null;
        $variant = $cartItem instanceof CoreOrderItemInterface ? $cartItem->getVariant() : null;

        if (!$channel instanceof ChannelInterface || null === $variant) {
            return null;
        }

        try {
            $price = $this->productVariantPricesCalculator->calculate($variant, ['channel' => $channel]);
        } catch (MissingChannelConfigurationException) {
            return null;
        }

        return $price > 0 ? $price : null;
    }
}
