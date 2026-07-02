<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Operates on gift cards that were BOUGHT on an order (as opposed to gift cards used to pay for an order)
 */
interface OrderGiftCardOperatorInterface
{
    /**
     * Called on checkout completion. Makes sure every gift card order item unit has a gift card (creating any
     * that are missing after a quantity change), snapshots the final amount from the paid unit total, associates
     * the customer and refreshes the expiry
     */
    public function reconcile(OrderInterface $order): void;

    /**
     * Called when the order is paid. Enables all gift cards bought on the order
     */
    public function enable(OrderInterface $order): void;

    /**
     * Called when the order is cancelled. Disables all gift cards bought on the order
     */
    public function disable(OrderInterface $order): void;
}
