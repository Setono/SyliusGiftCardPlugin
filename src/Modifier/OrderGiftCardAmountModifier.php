<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\Common\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardAmountModifier implements OrderGiftCardAmountModifierInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var ObjectManager */
    private $giftCardManager;

    public function __construct(GiftCardRepositoryInterface $giftCardCodeRepository, ObjectManager $giftCardManager)
    {
        $this->giftCardRepository = $giftCardCodeRepository;
        $this->giftCardManager = $giftCardManager;
    }

    /**
     * Calls on order creation
     */
    public function increment(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCard = $this->giftCardRepository->findOneByCode($code);

            if (null === $giftCard) {
                continue;
            }

            $amount = abs($adjustment->getAmount());

            if ($amount >= $giftCard->getAmount()) {
                $giftCard->disable();
                $giftCard->setAmount(0);
            }

            if ($amount < $giftCard->getAmount()) {
                $giftCard->enable();

                $giftCard->setAmount($giftCard->getAmount() - $amount);
            }

            $giftCard->addAppliedOrder($order);
        }

        $this->giftCardManager->flush();
    }

    /**
     * Calls on Order cancellation
     */
    public function decrement(OrderInterface $order): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT) as $adjustment) {
            $code = $adjustment->getOriginCode();

            $giftCard = $this->giftCardRepository->findOneByCode($code);

            if (null === $giftCard) {
                continue;
            }

            $giftCard->setAmount($giftCard->getAmount() + abs($adjustment->getAmount()));

            if ($giftCard->getAmount() > 0) {
                $giftCard->enable();
            }

            $giftCard->removeAppliedOrder($order);
        }

        $this->giftCardManager->flush();
    }
}
