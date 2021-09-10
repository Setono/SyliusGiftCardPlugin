<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Order\Dto\AddGiftCardToCartInformationInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

class AddToCartCommand implements AddToCartCommandInterface
{
    protected OrderInterface $cart;

    protected OrderItemInterface $cartItem;

    protected AddGiftCardToCartInformationInterface $giftCardInformation;

    public function __construct(
        OrderInterface $cart,
        OrderItemInterface $cartItem,
        AddGiftCardToCartInformationInterface $giftCardInformation
    ) {
        $this->cart = $cart;
        $this->cartItem = $cartItem;
        $this->giftCardInformation = $giftCardInformation;
    }

    public function getCart(): OrderInterface
    {
        return $this->cart;
    }

    public function getCartItem(): OrderItemInterface
    {
        return $this->cartItem;
    }

    public function getGiftCardInformation(): AddGiftCardToCartInformationInterface
    {
        return $this->giftCardInformation;
    }
}
