<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\OrderItemUnitInterface as BaseOrderItemUnitInterface;

interface OrderItemUnitInterface extends BaseOrderItemUnitInterface
{
    public function getGiftCard(): ?GiftCardInterface;

    public function setGiftCard(GiftCardInterface $giftCard): void;
}
