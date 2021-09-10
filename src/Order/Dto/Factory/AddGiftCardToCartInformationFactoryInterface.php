<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Dto\Factory;

use Setono\SyliusGiftCardPlugin\Order\Dto\AddGiftCardToCartInformationInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

interface AddGiftCardToCartInformationFactoryInterface
{
    public function createNew(OrderItemInterface $orderItem): AddGiftCardToCartInformationInterface;
}
