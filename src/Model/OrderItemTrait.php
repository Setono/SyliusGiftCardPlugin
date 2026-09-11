<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

trait OrderItemTrait
{
    /**
     * Gift card order items must never be merged with a *different* line: each add-to-cart yields its own line so it
     * can carry its own gift card(s) with distinct amount, message and design. An item must, however, still equal
     * itself — Sylius resolves the just-added item with `getItems()->filter(equals)->first()`, so returning false for
     * identity would make that resolution fail with a TypeError.
     */
    public function equals(BaseOrderItemInterface $item): bool
    {
        if ($this === $item) {
            return true;
        }

        if (!parent::equals($item)) {
            return false;
        }

        $product = $this->getProduct();

        return !($product instanceof ProductInterface && $product->isGiftCard());
    }
}
