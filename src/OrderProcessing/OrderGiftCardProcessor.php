<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessing;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderGiftCardProcessor implements OrderProcessorInterface
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var AdjustmentFactoryInterface */
    private $adjustmentFactory;

    public function __construct(
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        AdjustmentFactoryInterface $adjustmentFactory
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->adjustmentFactory = $adjustmentFactory;
    }

    public function process(OrderInterface $order): void
    {
        if (null === $order->getId()) {
            return;
        }

        /** @var GiftCardCodeInterface[] $giftCardCodes */
        $giftCardCodes = $this->giftCardCodeRepository->findAllActiveByCurrentOrder($order);

        $order->removeAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);

        foreach ($giftCardCodes as $giftCardCode) {
            $amount = $giftCardCode->getAmount();

            if ($order->getTotal() < $amount) {
                $amount = $order->getTotal();
            }

            if ($amount === 0) {
                continue;
            }

            $orderItem = $giftCardCode->getOrderItem();

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                null !== $orderItem ? $orderItem->getProductName() : AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                -1 * $amount
            );

            $adjustment->setOriginCode($giftCardCode->getCode());
            $adjustment->setAdjustable($order);

            $order->addAdjustment($adjustment);
        }
    }
}
