<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * The redemption method used when `setono_sylius_gift_card.redemption.mode` is "adjustment": gift cards reduce
 * the order total through negative adjustments and the balance is committed when the order is placed
 */
final class AdjustmentRedemptionMethod extends RedemptionMethod
{
    public function __construct(
        OrderProcessorInterface $orderProcessor,
        GiftCardCoverageCalculatorInterface $coverageCalculator,
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        private readonly GiftCardRepositoryInterface $giftCardRepository,
    ) {
        parent::__construct($orderProcessor, $coverageCalculator);
    }

    public function commit(OrderInterface $order): void
    {
        foreach ($this->getGiftCardAdjustments($order) as $adjustment) {
            $giftCard = $this->resolveGiftCard($adjustment->getOriginCode());
            if (null === $giftCard) {
                continue;
            }

            $this->balanceOperator->redeem(
                $giftCard,
                abs($adjustment->getAmount()),
                $order,
                null,
                sprintf('redeem:order:%s:gift_card:%s', (string) $order->getId(), (string) $giftCard->getId()),
            );
        }
    }

    public function rollback(OrderInterface $order): void
    {
        foreach ($this->getGiftCardAdjustments($order) as $adjustment) {
            $giftCard = $this->resolveGiftCard($adjustment->getOriginCode());
            if (null === $giftCard) {
                continue;
            }

            $this->balanceOperator->restore(
                $giftCard,
                abs($adjustment->getAmount()),
                $order,
                null,
                sprintf('restore:order:%s:gift_card:%s', (string) $order->getId(), (string) $giftCard->getId()),
            );
        }
    }

    /**
     * @return iterable<\Sylius\Component\Order\Model\AdjustmentInterface>
     */
    private function getGiftCardAdjustments(OrderInterface $order): iterable
    {
        return $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);
    }

    private function resolveGiftCard(?string $code): ?\Setono\SyliusGiftCardPlugin\Model\GiftCardInterface
    {
        if (null === $code) {
            return null;
        }

        return $this->giftCardRepository->findOneByCode($code);
    }
}
