<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Cart;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;

interface CartGiftCardHandlerInterface
{
    /**
     * Applies the customer supplied gift card information to a gift card cart item: it fixes the unit price to
     * the chosen amount and creates one pending (disabled) gift card per order item unit carrying the amount,
     * currency, delivery type, design and custom message
     */
    public function handle(AddToCartCommandInterface $command): void;
}
