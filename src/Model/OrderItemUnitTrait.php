<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait OrderItemUnitTrait
{
    #[ORM\OneToOne(targetEntity: GiftCardInterface::class, mappedBy: 'orderItemUnit')]
    protected ?GiftCardInterface $giftCard = null;

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(GiftCardInterface $giftCard): void
    {
        if ($this->giftCard === $giftCard) {
            return;
        }

        $this->giftCard = $giftCard;

        $giftCard->setOrderItemUnit($this);
    }
}
