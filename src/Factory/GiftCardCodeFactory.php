<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Entity\GiftCardCode;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class GiftCardCodeFactory implements GiftCardCodeFactoryInterface
{
    public function createWithGiftCardAndOrderItem(GiftCardInterface $giftCard, OrderItemInterface $orderItem): GiftCardCodeInterface
    {
        $giftCardCode = $this->createNew();

        $giftCardCode->setGiftCard($giftCard);
        $giftCardCode->setOrderItem($orderItem);

        return $giftCardCode;
    }

    public function createNew(): GiftCardCodeInterface
    {
        return new GiftCardCode();
    }
}
