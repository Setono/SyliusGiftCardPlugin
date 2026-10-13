<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * A redemption method decides what happens when a gift card is used to pay for an order. The seam is kept so an
 * application can substitute its own, but the plugin ships one implementation: a redeemed gift card becomes a
 * real payment against the order rather than a discount on it
 */
interface GiftCardRedemptionMethodInterface
{
    /**
     * Attaches the gift card to the (cart) order and reprocesses it
     */
    public function apply(OrderInterface $order, GiftCardInterface $giftCard): void;

    /**
     * Detaches the gift card from the (cart) order and reprocesses it
     */
    public function remove(OrderInterface $order, GiftCardInterface $giftCard): void;

    /**
     * What each gift card applied to the order pays on it, in the order's currency and in the card's. Until the order's
     * redemption is committed (commit()), that is what their balances cover now: on a cart, and on an order whose checkout
     * is completed before Sylius has created it and the plugin has committed it. From then on, it is what they paid when
     * the order was placed, which their balances no longer tell (#498), and a card whose payment was refunded covers
     * nothing
     */
    public function getCoverage(OrderInterface $order): GiftCardCoverage;

    /**
     * The total amount (minor units) the applied gift cards cover on the order, as getCoverage() tells it
     */
    public function getCoveredAmount(OrderInterface $order): int;

    /**
     * The amount (minor units) a single gift card covers on the order, as getCoverage() tells it
     */
    public function getCoveredAmountByGiftCard(OrderInterface $order, GiftCardInterface $giftCard): int;

    /**
     * Materializes the redemption and commits the balance changes. Called when the order is placed. Must be idempotent
     */
    public function commit(OrderInterface $order): void;

    /**
     * Reverses the redemption and restores the balances. Called when the order is cancelled. Must be idempotent
     */
    public function rollback(OrderInterface $order): void;

    /**
     * Gives a gift card back what one of its payments took, once that payment has been refunded. Called after the
     * payment's refund transition, whether an admin refunded it by hand or rollback() did, and must be idempotent
     * per payment. Does nothing for a payment that is not a gift card payment or has not been refunded
     */
    public function rollbackPayment(PaymentInterface $payment): void;
}
