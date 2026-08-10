<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * An append-only ledger row recording a single balance mutation of a gift card.
 * Rows are only ever written by the gift card balance operator
 */
interface GiftCardTransactionInterface extends ResourceInterface
{
    /**
     * The opening balance a gift card was issued with. Recorded once per card, so that the ledger accounts
     * for the whole balance rather than only the movements after issuance
     */
    public const TYPE_ISSUE = 'issue';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_RESTORE = 'restore';

    public const TYPE_MANUAL = 'manual';

    public function getId(): ?int;

    public function getGiftCard(): ?GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;

    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): void;

    public function getPayment(): ?PaymentInterface;

    public function setPayment(?PaymentInterface $payment): void;

    /**
     * The balance mutation in minor units. Negative when redeeming, positive when restoring
     */
    public function getAmount(): int;

    public function setAmount(int $amount): void;

    public function getType(): string;

    public function setType(string $type): void;

    public function getReason(): ?string;

    public function setReason(?string $reason): void;

    /**
     * A deterministic key making state machine callbacks idempotent.
     * Null for manual adjustments (multiple manual rows are always allowed)
     */
    public function getIdempotencyKey(): ?string;

    public function setIdempotencyKey(?string $idempotencyKey): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(?\DateTimeInterface $createdAt): void;
}
