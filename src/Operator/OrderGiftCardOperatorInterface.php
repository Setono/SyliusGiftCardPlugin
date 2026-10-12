<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Operates on gift cards that were BOUGHT on an order (as opposed to gift cards used to pay for an order).
 *
 * None of these methods flush: they run as state machine callbacks, and whatever applies the transition (Sylius'
 * resource controller, Payum's storage after a capture or notify, the unpaid order canceller) flushes afterwards
 */
interface OrderGiftCardOperatorInterface
{
    /**
     * Called on checkout completion. Makes sure every gift card order item unit has a gift card (creating any
     * that are missing after a quantity change), sets each card's amount to the amount the customer chose (the unit
     * price of its line), associates the customer and stamps the expiry.
     *
     * A bought gift card's validity counts from the purchase, i.e. from here, not from when it was added to the
     * cart nor from when the order is paid. Every card bought on the order gets the same expiry, the one
     * GiftCardExpiryResolverInterface resolves now, or none when gift cards are configured to never expire
     */
    public function reconcile(OrderInterface $order): void;

    /**
     * Called whenever the cart is processed. Gives every unit of a gift card line that has no gift card yet, one added
     * by raising the quantity on the cart page, a pending card held like the line's other cards: in their currency and
     * amount, with their design, message and delivery type. Sylius drops units from the front of a line when the
     * quantity is lowered, so a line raised and lowered again would otherwise lose the card add to cart gave it, and
     * with it the currency and the amount the customer chose (#488). A line without any card is left to reconcile()
     */
    public function completeLines(OrderInterface $order): void;

    /**
     * Called when the order is paid. Enables all gift cards bought on the order and records their issuance in the
     * ledger, against the order
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
