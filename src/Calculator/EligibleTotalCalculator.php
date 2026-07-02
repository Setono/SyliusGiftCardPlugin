<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class EligibleTotalCalculator implements EligibleTotalCalculatorInterface
{
    public function getEligibleTotal(OrderInterface $order): int
    {
        $total = $order->getTotal();

        // Exclude gift card adjustments already applied to the order so that the eligible total (and therefore the
        // per-card coverage) is computed against the pre-redemption total. Without this the coverage shown for an
        // applied card collapses to 0 once its own adjustment has reduced the order total.
        $total -= $order->getAdjustmentsTotal(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product instanceof ProductInterface && $product->isGiftCard()) {
                $total -= $item->getTotal();
            }
        }

        return max(0, $total);
    }
}
