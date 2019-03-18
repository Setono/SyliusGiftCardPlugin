<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

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

    /**
     * {@inheritdoc}
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    /**
     * {@inheritdoc}
     */
    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }

    /**
     * {@inheritdoc}
     */
    public function getGiftCardCodes(): Collection
    {
        return $this->giftCardCodes;
    }

    /**
     * {@inheritdoc}
     */
    public function addGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if (!$this->hasGiftCardCode($giftCardCode)) {
            $giftCardCode->setGiftCard($this);
            $this->giftCardCodes->add($giftCardCode);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeGiftCardCode(GiftCardCodeInterface $giftCardCode): void
    {
        if ($this->hasGiftCardCode($giftCardCode)) {
            $giftCardCode->setGiftCard(null);
            $this->giftCardCodes->removeElement($giftCardCode);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasGiftCardCode(GiftCardCodeInterface $giftCardCode): bool
    {
        return $this->giftCardCodes->contains($giftCardCode);
    }
}
