<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait OrderTrait
{
    /**
     * @var Collection|GiftCardCodeInterface[]
     *
     * @ORM\ManyToMany(targetEntity="\Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface", mappedBy="usedInOrders")
     */
    protected $payedByGiftCardCodes;

    /**
     * @return Collection|GiftCardCodeInterface[]
     */
    public function getPayedByGiftCardCodes(): Collection
    {
        return $this->payedByGiftCardCodes;
    }

    /**
     * @param GiftCardCodeInterface $giftCardCode
     * @return bool
     */
    public function hasPayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): bool
    {
        return $this->payedByGiftCardCodes->contains($giftCardCode);
    }

    /**
     * @param GiftCardCodeInterface $giftCardCode
     */
    public function addPayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if (!$this->hasPayedByGiftCardCode($giftCardCode)) {
            $this->payedByGiftCardCodes->add($giftCardCode);
        }
    }

    /**
     * @param GiftCardCodeInterface $giftCardCode
     */
    public function removePayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if ($this->hasPayedByGiftCardCode($giftCardCode)) {
            $this->payedByGiftCardCodes->removeElement($giftCardCode);
        }
    }
}
