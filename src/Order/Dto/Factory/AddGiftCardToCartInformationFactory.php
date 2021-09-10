<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Dto\Factory;

use Setono\SyliusGiftCardPlugin\Order\Dto\AddGiftCardToCartInformationInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class AddGiftCardToCartInformationFactory implements AddGiftCardToCartInformationFactoryInterface
{
    private string $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function createNew(OrderItemInterface $orderItem): AddGiftCardToCartInformationInterface
    {
        /**
         * @var AddGiftCardToCartInformationInterface $giftCardInformation
         * @psalm-suppress InvalidStringClass
         */
        $giftCardInformation = new $this->className($orderItem->getUnitPrice());

        return $giftCardInformation;
    }
}
