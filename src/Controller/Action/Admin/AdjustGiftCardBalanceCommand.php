<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class AdjustGiftCardBalanceCommand
{
    /** Minor units, positive to increase the balance and negative to decrease it */
    #[Assert\NotNull(groups: ['setono_sylius_gift_card'])]
    #[Assert\NotEqualTo(value: 0, groups: ['setono_sylius_gift_card'])]
    private ?int $amount = null;

    #[Assert\NotBlank(groups: ['setono_sylius_gift_card'])]
    private ?string $reason = null;

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
