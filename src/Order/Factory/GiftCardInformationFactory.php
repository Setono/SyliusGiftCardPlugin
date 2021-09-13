<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class GiftCardInformationFactory implements GiftCardInformationFactoryInterface
{
    /** @var class-string<GiftCardInformationInterface> */
    private string $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function createNew(OrderItemInterface $orderItem): GiftCardInformationInterface
    {
        return new $this->className($orderItem->getUnitPrice());
    }
}
