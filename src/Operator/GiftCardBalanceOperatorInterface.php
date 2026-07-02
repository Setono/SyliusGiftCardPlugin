<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * The single point that mutates a gift card's balance. Every mutation writes an append-only ledger transaction.
 * None of these methods flush; the surrounding unit of work is responsible for that
 */
interface GiftCardBalanceOperatorInterface
{
    /**
     * Decreases the gift card balance by $amount.
     *
     * @param string|null $idempotencyKey when given, a second call with the same key is a no-op, which makes
     *                                     state machine callbacks safe to re-fire
     *
     * @throws \Setono\SyliusGiftCardPlugin\Exception\InsufficientGiftCardBalanceException if $amount exceeds the balance
     */
    public function redeem(
        GiftCardInterface $giftCard,
        int $amount,
        ?OrderInterface $order = null,
        ?PaymentInterface $payment = null,
        ?string $idempotencyKey = null,
    ): void;

    /**
     * Increases the gift card balance by $amount (e.g. when an order is cancelled or a payment refunded).
     *
     * @param string|null $idempotencyKey when given, a second call with the same key is a no-op
     */
    public function restore(
        GiftCardInterface $giftCard,
        int $amount,
        ?OrderInterface $order = null,
        ?PaymentInterface $payment = null,
        ?string $idempotencyKey = null,
    ): void;

    /**
     * Manually changes the balance by $delta (positive or negative) for administrative reasons, recording the reason
     */
    public function adjust(GiftCardInterface $giftCard, int $delta, string $reason): void;
}
