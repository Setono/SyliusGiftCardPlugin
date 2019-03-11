<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardsUsageModifier implements OrderGiftCardsUsageModifierInterface
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardCodeEntityManager;

    public function __construct(GiftCardCodeRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardCodeEntityManager)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
    }

    /**
     * @param OrderInterface $order
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
                $giftCardCode->setActive(false);
                $giftCardCode->setAmount(0);
            }

            if ($amount < $giftCardCode->getAmount()) {
                $giftCardCode->setActive(true);

                $giftCardCode->setAmount($giftCardCode->getAmount() - $amount);
            }

            $giftCardCode->addUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush();
        }
    }

    /**
     * @param OrderInterface $order
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
                $giftCardCode->setActive(true);
            }

            $giftCardCode->removeUsedInOrder($order);

            $this->giftCardCodeEntityManager->flush();
        }
    }
}
