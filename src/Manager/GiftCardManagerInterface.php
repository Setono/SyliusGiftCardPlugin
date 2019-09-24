<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Manager;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface GiftCardManagerInterface
{
    /**
     * Will create and persist all gift cards on the given order
     */
    public function createFromOrder(OrderInterface $order): void;

    public function enableGiftCard(GiftCardInterface $giftCard): void;
}
