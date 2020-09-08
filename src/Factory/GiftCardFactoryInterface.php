<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface GiftCardFactoryInterface extends FactoryInterface
{
    public function createNew(): GiftCardInterface;

    public function createForChannel(ChannelInterface $channel): GiftCardInterface;

    public function createFromOrderItemUnit(OrderItemUnitInterface $orderItemUnit): GiftCardInterface;
}
