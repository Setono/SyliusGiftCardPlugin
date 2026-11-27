<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\InitialGiftCardAmountResolverInterface;
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
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
        private readonly InitialGiftCardAmountResolverInterface $initialAmountResolver,
    ) {
    }

    public function createNew(OrderInterface $cart, OrderItemInterface $cartItem): GiftCardInformationInterface
    {
        $channel = $cart instanceof CoreOrderInterface ? $cart->getChannel() : null;

        // The card is held in the currency the customer browses in, when the channel offers it, so the amount is typed
        // in that currency too (#488)
        $currencyCode = $channel instanceof ChannelInterface ? $this->currencyProvider->getCurrencyCode($channel) : null;

        // The amount field starts out at the amount the resolver suggests for the line's variant, and empty without a
        // channel to suggest one in
        $amount = null;
        if ($channel instanceof ChannelInterface && null !== $currencyCode) {
            $variant = $cartItem instanceof CoreOrderItemInterface ? $cartItem->getVariant() : null;
            $amount = $this->initialAmountResolver->resolve($channel, $currencyCode, $variant);
        }

        $information = new $this->className($amount);
        $information->setCurrencyCode($currencyCode);

        return $information;
    }
}
