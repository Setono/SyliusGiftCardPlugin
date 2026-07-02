<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface as GiftCardOrderInterface;
use Sylius\Component\Core\Checker\OrderPaymentMethodSelectionRequirementCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * In "payment" redemption mode the customer does not need to pick a payment method when gift cards cover the whole
 * order, even though the order total is not zero
 */
final class GiftCardAwarePaymentMethodSelectionRequirementChecker implements OrderPaymentMethodSelectionRequirementCheckerInterface
{
    public function __construct(
        private readonly OrderPaymentMethodSelectionRequirementCheckerInterface $decorated,
        private readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
    ) {
    }

    public function isPaymentMethodSelectionRequired(OrderInterface $order): bool
    {
        if ($order instanceof GiftCardOrderInterface) {
            $remaining = $order->getTotal() - $this->coverageCalculator->calculate($order)->getTotal();
            if ($remaining <= 0) {
                return false;
            }
        }

        return $this->decorated->isPaymentMethodSelectionRequired($order);
    }
}
