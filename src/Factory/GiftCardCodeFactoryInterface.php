<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface GiftCardCodeFactoryInterface extends FactoryInterface
{
    /**
     * @param ChannelInterface $channel
     *
     * @return GiftCardCodeInterface
     */
    public function createForChannel(ChannelInterface $channel): GiftCardCodeInterface;

    /**
     * @param GiftCardInterface $giftCard
     *
     * @return GiftCardCodeInterface
     */
    public function createForGiftCard(GiftCardInterface $giftCard): GiftCardCodeInterface;

    /**
     * @param GiftCardInterface $giftCard
     * @param OrderItemInterface $orderItem
     *
     * @return GiftCardCodeInterface
     */
    public function createForGiftCardAndOrderItem(GiftCardInterface $giftCard, OrderItemInterface $orderItem): GiftCardCodeInterface;
}
