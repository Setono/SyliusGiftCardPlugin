<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait OrderTrait
{
    /**
     * @var Collection|GiftCardInterface[]
     *
     * @ORM\ManyToMany(targetEntity="GiftCardInterface", mappedBy="usedInOrders")
     */
    protected $payedByGiftCardCodes;

    /**
     * @return Collection|GiftCardInterface[]
     */
    public function getPayedByGiftCardCodes(): Collection
    {
        return $this->payedByGiftCardCodes;
    }

    public function hasPayedByGiftCardCode(GiftCardInterface $giftCardCode): bool
    {
        return $this->payedByGiftCardCodes->contains($giftCardCode);
    }

    public function addPayedByGiftCardCode(GiftCardInterface $giftCardCode): void
    {
        if (!$this->hasPayedByGiftCardCode($giftCardCode)) {
            $this->payedByGiftCardCodes->add($giftCardCode);
        }
    }

    public function removePayedByGiftCardCode(GiftCardInterface $giftCardCode): void
    {
        if ($this->hasPayedByGiftCardCode($giftCardCode)) {
            $this->payedByGiftCardCodes->removeElement($giftCardCode);
        }
    }
}
