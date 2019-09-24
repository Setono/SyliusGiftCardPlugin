<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardCodeFactory implements GiftCardCodeFactoryInterface
{
    /** @var FactoryInterface */
    private $factory;

    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function createNew(): GiftCardCodeInterface
    {
        /** @var GiftCardCodeInterface $giftCardCode */
        $giftCardCode = $this->factory->createNew();

        return $giftCardCode;
    }

    public function createForChannel(ChannelInterface $channel): GiftCardCodeInterface
    {
        $giftCardCode = $this->createNew();

        $giftCardCode->setChannel($channel);

        return $giftCardCode;
    }

    public function createForGiftCard(GiftCardInterface $giftCard): GiftCardCodeInterface
    {
        $giftCardCode = $this->createNew();

        $giftCardCode->setGiftCard($giftCard);

        return $giftCardCode;
    }

    public function createForGiftCardAndOrderItem(GiftCardInterface $giftCard, OrderItemInterface $orderItem): GiftCardCodeInterface
    {
        $giftCardCode = $this->createForGiftCard($giftCard);

        $giftCardCode->setOrderItem($orderItem);

        return $giftCardCode;
    }
}
