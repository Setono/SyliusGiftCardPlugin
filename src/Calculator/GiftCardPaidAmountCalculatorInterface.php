<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardPaidAmountCalculatorInterface
{
    /**
     * Returns the amount (in minor units) the gift cards have already paid on the order, i.e. the sum of its
     * completed gift card payments.
     *
     * This is the placed-order counterpart of the coverage calculator. While the order is a cart, what the gift
     * cards will cover is computed from their live balances. At order placement each applied gift card is redeemed
     * and becomes a completed payment, after which its balance says nothing about this order any more (it is spent,
     * possibly on other orders by now), but its payment does
     */
    public function getPaidAmount(OrderInterface $order): int;
}
