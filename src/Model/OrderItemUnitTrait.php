<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItemUnit;

trait OrderItemUnitTrait
{
    /**
     * @var GiftCardInterface|null
     *
     * @ORM\OneToOne (targetEntity="Setono\SyliusGiftCardPlugin\Model\GiftCardInterface", mappedBy="orderItemUnit")
     */
    protected $giftCard;

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
