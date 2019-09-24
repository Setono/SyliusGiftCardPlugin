<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @return Collection|GiftCardInterface[]
     */
    public function getPayedByGiftCardCodes(): Collection;

    public function hasPayedByGiftCardCode(GiftCardInterface $giftCardCode): bool;

    public function addPayedByGiftCardCode(GiftCardInterface $giftCardCode): void;

    public function removePayedByGiftCardCode(GiftCardInterface $giftCardCode): void;
}
