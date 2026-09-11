<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Sylius\Component\Core\Model\OrderInterface;

interface EligibleTotalCalculatorInterface
{
    /**
     * Returns the part of the order total (in minor units) that gift cards are allowed to pay for.
     *
     * By default this is the whole order total minus any gift card product line items, so a gift card cannot be
     * used to buy another gift card. Decorate this service to change what gift cards may pay for
     */
    public function getEligibleTotal(OrderInterface $order): int;
}
