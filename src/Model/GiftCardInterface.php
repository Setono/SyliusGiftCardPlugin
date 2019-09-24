<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface GiftCardInterface extends ResourceInterface
{
    public function getProduct(): ?ProductInterface;

    public function setProduct(?ProductInterface $product): void;

    public function getGiftCardCodes(): Collection;

    public function addGiftCardCode(GiftCardCodeInterface $giftCardCode): void;

    public function removeGiftCardCode(GiftCardCodeInterface $giftCardCode): void;

    public function hasGiftCardCode(GiftCardCodeInterface $giftCardCode): bool;
}
