<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

interface GiftCardInformationFactoryInterface
{
    public function createNew(OrderItemInterface $orderItem): GiftCardInformationInterface;
}
