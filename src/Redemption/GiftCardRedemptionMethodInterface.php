<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

/**
 * A redemption method decides what happens when a gift card is used to pay for an order. There are two
 * implementations selected by the `setono_sylius_gift_card.redemption.mode` configuration: one that creates
 * order adjustments and one that creates real payment entities
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
     * The total amount (minor units) the applied gift cards cover on the order
     */
    public function getCoveredAmount(OrderInterface $order): int;

    /**
     * The amount (minor units) a single gift card covers on the order
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
}
