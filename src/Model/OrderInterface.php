<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @return Collection|GiftCardCodeInterface[]
     */
    public function getPayedByGiftCardCodes(): Collection;

    /**
     * @param GiftCardCodeInterface $giftCardCode
     * @return bool
     */
    public function hasPayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): bool;

    /**
     * @param GiftCardCodeInterface $giftCardCode
     */
    public function addPayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): void;

    /**
     * @param GiftCardCodeInterface $giftCardCode
     */
    public function removePayedByGiftCardCode(GiftCardCodeInterface $giftCardCode): void;
}
