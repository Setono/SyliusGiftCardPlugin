<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * Carries the gift card it adjusts, so the command describes the whole intent and validation can judge the
 * adjustment against the balance it applies to. Constraints live in
 * Resources/config/validation/AdjustGiftCardBalanceCommand.xml
 */
final class AdjustGiftCardBalanceCommand
{
    /** Minor units, positive to increase the balance and negative to decrease it */
    private ?int $amount = null;

    private ?string $reason = null;

    public function __construct(private readonly GiftCardInterface $giftCard)
    {
    }

    public function getGiftCard(): GiftCardInterface
    {
        return $this->giftCard;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }
}
