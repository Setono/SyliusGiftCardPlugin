<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

interface GiftCardInformationFactoryInterface
{
    /**
     * Creates the information the add to cart form starts out with on the product page. The cart item is not in the
     * cart yet, which is why the cart it is about to be added to is passed along
     */
    public function createNew(OrderInterface $cart, OrderItemInterface $cartItem): GiftCardInformationInterface;
}
