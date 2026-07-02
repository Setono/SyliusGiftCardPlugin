<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

class AddToCartCommand implements AddToCartCommandInterface
{
    public function __construct(protected OrderInterface $cart, protected OrderItemInterface $cartItem, protected GiftCardInformationInterface $giftCardInformation)
    {
    }

    public function getCart(): OrderInterface
    {
        return $this->cart;
    }

    public function getCartItem(): OrderItemInterface
    {
        return $this->cartItem;
    }

    public function getGiftCardInformation(): GiftCardInformationInterface
    {
        return $this->giftCardInformation;
    }
}
