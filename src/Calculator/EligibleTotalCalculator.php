<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class EligibleTotalCalculator implements EligibleTotalCalculatorInterface
{
    public function getEligibleTotal(OrderInterface $order): int
    {
        $total = $order->getTotal();

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product instanceof ProductInterface && $product->isGiftCard()) {
                $total -= $item->getTotal();
            }
        }

        return max(0, $total);
    }
}
