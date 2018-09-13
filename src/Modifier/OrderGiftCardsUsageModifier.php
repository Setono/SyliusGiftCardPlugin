<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Entity\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardsUsageModifier implements OrderGiftCardsUsageModifierInterface
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface|EntityManager */
    private $giftCardCodeEntityManager;

    public function __construct(GiftCardCodeRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardCodeEntityManager)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
    }

    public function increment(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

            $amount = abs($adjustment->getAmount());

            if ($amount >= $giftCardCode->getAmount()) {
                $giftCardCode->setIsActive(false);
                $giftCardCode->setAmount(0);
            }

            if ($amount < $giftCardCode->getAmount()) {
                $giftCardCode->setIsActive(true);

                $giftCardCode->setAmount(($giftCardCode->getAmount() - $amount));
            }

            $giftCardCode->addUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush($giftCardCode);
        }
    }

    public function decrement(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCardCode = $this->giftCardCodeRepository->findOneByCode($code);

            $giftCardCode->setAmount($giftCardCode->getAmount() + abs($adjustment->getAmount()));

            if ($giftCardCode->getAmount() > 0) {
                $giftCardCode->setIsActive(true);
            }

            $giftCardCode->removeUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush($giftCardCode);
        }
    }
}
