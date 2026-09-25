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
     * the customer and stamps the expiry.
     *
     * A bought gift card's validity counts from the purchase, i.e. from here, not from when it was added to the
     * cart nor from when the order is paid. Every card bought on the order gets the same expiry, the one
     * GiftCardExpiryResolverInterface resolves now, or none when gift cards are configured to never expire
     */
    public function reconcile(OrderInterface $order): void;

    /**
     * Called when the order is paid. Enables all gift cards bought on the order
     */
    public function enable(OrderInterface $order): void;

    /**
     * Called when the order is paid. Emails the gift cards bought on the order to the customer
     */
    public function send(OrderInterface $order): void;

    /**
     * Called when the order is cancelled. Disables all gift cards bought on the order
     */
    public function disable(OrderInterface $order): void;
}
