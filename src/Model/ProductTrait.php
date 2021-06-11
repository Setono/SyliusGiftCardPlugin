<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ProductTrait
{
    /** @ORM\Column(type="boolean", options={"default": false}) */
    protected bool $giftCard = false;

    public function isGiftCard(): bool
    {
        return $this->giftCard;
    }

    public function setGiftCard(bool $isGiftCard): void
    {
        $this->giftCard = $isGiftCard;
    }
}
