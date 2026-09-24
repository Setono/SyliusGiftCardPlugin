<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardCoverageCalculatorInterface
{
    /**
     * Computes how much each gift card applied to the order covers, capping the running total at the eligible
     * total so multiple gift cards stack correctly and never over-cover. Gift cards the eligibility checker rejects
     * for the order (disabled, expired, empty, another channel or currency) contribute nothing
     */
    public function calculate(OrderInterface $order): GiftCardCoverage;
}
