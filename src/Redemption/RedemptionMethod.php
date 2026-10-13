<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Redemption;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Base class holding the behaviour shared by all redemption methods: attaching/detaching gift cards to the
 * Order (via the many-to-many relation) and computing coverage. Subclasses implement commit()/rollback()
 */
abstract class RedemptionMethod implements GiftCardRedemptionMethodInterface
{
    public function __construct(
        protected readonly OrderProcessorInterface $orderProcessor,
        protected readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
    ) {
    }

    public function apply(OrderInterface $order, GiftCardInterface $giftCard): void
    {
        $order->addGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    public function remove(OrderInterface $order, GiftCardInterface $giftCard): void
    {
        $order->removeGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    /**
     * What the cards' balances cover now. A redemption method that records what the cards paid when the order was
     * placed tells that instead for a placed order
     */
    public function getCoverage(OrderInterface $order): GiftCardCoverage
    {
        return $this->coverageCalculator->calculate($order);
    }

    public function getCoveredAmount(OrderInterface $order): int
    {
        return $this->getCoverage($order)->getTotal();
    }

    public function getCoveredAmountByGiftCard(OrderInterface $order, GiftCardInterface $giftCard): int
    {
        return $this->getCoverage($order)->getAmountForGiftCard($giftCard);
    }
}
