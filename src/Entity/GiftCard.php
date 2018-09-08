<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ProductInterface;

class GiftCard implements GiftCardInterface
{
    /** @var int|null */
    protected $id;

    /** @var ProductInterface|null */
    protected $product;

    /** @var Collection|GiftCardCodeInterface[] */
    protected $giftCardCodes;

    public function __construct()
    {
        $this->giftCardCodes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }

    public function getGiftCardCodes(): Collection
    {
        return $this->giftCardCodes;
    }

    public function addGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if (!$this->hasGiftCardCode($giftCardCode)) {
            $giftCardCode->setGiftCard($this);
            $this->giftCardCodes->add($giftCardCode);
        }
    }

    public function removeGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if ($this->hasGiftCardCode($giftCardCode)) {
            $giftCardCode->setGiftCard(null);
            $this->giftCardCodes->removeElement($giftCardCode);
        }
    }

    public function hasGiftCardCode(GiftCardCodeInterface $giftCardCode): bool
    {
        return $this->giftCardCodes->contains($giftCardCode);
    }
}
