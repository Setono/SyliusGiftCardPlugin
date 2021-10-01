<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @psalm-return Collection<array-key, GiftCardInterface>
     *
     * @return Collection|GiftCardInterface[]
     */
    public function getGiftCards(): Collection;

    public function hasGiftCards(): bool;

    public function addGiftCard(GiftCardInterface $giftCard): void;

    public function removeGiftCard(GiftCardInterface $giftCard): void;

    public function hasGiftCard(GiftCardInterface $giftCard): bool;
}
