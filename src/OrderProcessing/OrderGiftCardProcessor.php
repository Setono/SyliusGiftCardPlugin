<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessing;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

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

        $order->removeAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);

        if ($order->isEmpty()) {
            return;
        }

        /** @var GiftCardInterface[] $giftCards */
        $giftCards = $this->giftCardRepository->findActiveByCurrentOrder($order);

        foreach ($giftCards as $giftCard) {
            $amount = $giftCard->getAmount();

            if ($order->getTotal() < $amount) {
                $amount = $order->getTotal();
            }

            if (0 === $amount) {
                continue;
            }

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $giftCard->getCode(), // todo probably prepend 'Gift card' as a translated string
                -1 * $amount
            );

            $adjustment->setOriginCode($giftCard->getCode());
            $adjustment->setAdjustable($order);

            $order->addAdjustment($adjustment);
        }
    }
}
