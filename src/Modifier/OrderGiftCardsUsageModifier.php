<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardsUsageModifier implements OrderGiftCardsUsageModifierInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardCodeEntityManager;

    public function __construct(GiftCardRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardCodeEntityManager)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
    }

    /**
     * Calls on order creation
     */
    public function increment(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

            if (null === $giftCardCode) {
                continue;
            }

            $amount = abs($adjustment->getAmount());

            if ($amount >= $giftCardCode->getAmount()) {
                $giftCardCode->disable();
                $giftCardCode->setAmount(0);
            }

            if ($amount < $giftCardCode->getAmount()) {
                $giftCardCode->enable();

                $giftCardCode->setAmount($giftCardCode->getAmount() - $amount);
            }

            $giftCardCode->addUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush();
        }
    }

    /**
     * Calls on Order cancellation
     */
    public function decrement(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

            if (null === $giftCardCode) {
                continue;
            }

            $giftCardCode->setAmount($giftCardCode->getAmount() + abs($adjustment->getAmount()));

            if ($giftCardCode->getAmount() > 0) {
                $giftCardCode->enable();
            }

            $giftCardCode->removeUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush();
        }
    }
}
