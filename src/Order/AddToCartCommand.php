<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class AddToCartCommand implements AddToCartCommandInterface
{
    private OrderInterface $cart;

    private OrderItemInterface $cartItem;

    private GiftCardInformationInterface $giftCardInformation;

    public function __construct(
        OrderInterface $cart,
        OrderItemInterface $cartItem,
        GiftCardInformationInterface $giftCardInformation,
    ) {
        $this->cart = $cart;
        $this->cartItem = $cartItem;
        $this->giftCardInformation = $giftCardInformation;
    }

    #[\Override]
    public function getCart(): OrderInterface
    {
        return $this->cart;
    }

    #[\Override]
    public function getCartItem(): OrderItemInterface
    {
        return $this->cartItem;
    }

    #[\Override]
    public function getGiftCardInformation(): GiftCardInformationInterface
    {
        return $this->giftCardInformation;
    }
}
