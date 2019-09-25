<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessing;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardProcessor implements OrderProcessorInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var AdjustmentFactoryInterface */
    private $adjustmentFactory;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        AdjustmentFactoryInterface $adjustmentFactory
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->adjustmentFactory = $adjustmentFactory;
    }

    public function process(OrderInterface $order): void
    {
        if (null === $order->getId()) {
            return;
        }

        /** @var GiftCardInterface[] $giftCardCodes */
        $giftCardCodes = $this->giftCardRepository->findActiveByCurrentOrder($order);

        $order->removeAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);

        foreach ($giftCardCodes as $giftCardCode) {
            $amount = $giftCardCode->getAmount();

            if ($order->getTotal() < $amount) {
                $amount = $order->getTotal();
            }

            if ($amount === 0) {
                continue;
            }

            $orderItemUnit = $giftCardCode->getOrderItemUnit();
            Assert::notNull($orderItemUnit);

            /** @var OrderItemInterface $orderItem */
            $orderItem = $orderItemUnit->getOrderItem();

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $orderItem->getProductName(),
                -1 * $amount
            );

            $adjustment->setOriginCode($giftCardCode->getCode());
            $adjustment->setAdjustable($order);

            $order->addAdjustment($adjustment);
        }
    }
}
