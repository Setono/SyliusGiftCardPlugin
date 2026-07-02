<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

trait OrderItemTrait
{
    /**
     * Gift card order items must never be merged in the cart: each add-to-cart yields its own line
     * so it can carry its own gift card(s) with distinct amount, message and design
     */
    public function equals(BaseOrderItemInterface $item): bool
    {
        if (!parent::equals($item)) {
            return false;
        }

        $product = $this->getProduct();

        return !($product instanceof ProductInterface && $product->isGiftCard());
    }
}
