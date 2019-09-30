<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait OrderTrait
{
    /**
     * @var Collection|GiftCardInterface[]
     *
     * @ORM\ManyToMany(targetEntity="Setono\SyliusGiftCardPlugin\Model\GiftCardInterface", inversedBy="appliedOrders")
     * @ORM\JoinTable(name="setono_sylius_gift_card__order_gift_cards",
     *     joinColumns={@ORM\JoinColumn(name="order_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="gift_card_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    protected $giftCards;

    public function __construct()
    {
        $this->giftCards = new ArrayCollection();
    }

    public function getGiftCards(): Collection
    {
        return $this->giftCards;
    }

    public function hasGiftCards(): bool
    {
        return !$this->giftCards->isEmpty();
    }

    public function addGiftCard(GiftCardInterface $giftCard): void
    {
        if (!$this->hasGiftCard($giftCard)) {
            $this->giftCards->add($giftCard);
            $giftCard->addAppliedOrder($this);
        }
    }

    public function removeGiftCard(GiftCardInterface $giftCard): void
    {
        if ($this->hasGiftCard($giftCard)) {
            $this->giftCards->removeElement($giftCard);
            $giftCard->removeAppliedOrder($this);
        }
    }

    public function hasGiftCard(GiftCardInterface $giftCard): bool
    {
        return $this->giftCards->contains($giftCard);
    }
}
