<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsApplicable;
use Symfony\Component\Validator\Constraints as Assert;

final class AddGiftCardToOrderCommand
{
    #[Assert\NotBlank(groups: ['setono_sylius_gift_card'])]
    #[GiftCardIsApplicable(groups: ['setono_sylius_gift_card'])]
    private ?GiftCardInterface $giftCard = null;

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
