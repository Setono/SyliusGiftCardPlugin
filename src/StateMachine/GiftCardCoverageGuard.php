<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\StateMachine;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class GiftCardCoverageGuard implements GiftCardCoverageGuardInterface
{
    public function __construct(
        private readonly GiftCardEligibilityCheckerInterface $eligibilityChecker,
        private readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
    ) {
    }

    public function isSatisfiedBy(CoreOrderInterface $order): bool
    {
        if (!$order instanceof OrderInterface || !$order->hasGiftCards()) {
            return true;
        }

        return [] === $this->getIneligibleGiftCards($order) && $this->isTotalCovered($order);
    }

    public function getIneligibleGiftCards(OrderInterface $order): array
    {
        $giftCards = [];

        foreach ($order->getGiftCards() as $giftCard) {
            if (null !== $this->eligibilityChecker->getIneligibilityReason($giftCard, $order)) {
                $giftCards[] = $giftCard;
            }
        }

        return $giftCards;
    }

    public function isTotalCovered(OrderInterface $order): bool
    {
        // Coverage is computed from the cards' live balances, so a card spent elsewhere since it was applied
        // contributes nothing here even though the gateway payment was sized (or removed) as if it still did
        $covered = $this->coverageCalculator->calculate($order)->getTotal();

        foreach ($order->getPayments() as $payment) {
            // The gift card payments are only created once the order is placed; any found here would be counted
            // twice, once as coverage and once as a payment
            if ($payment instanceof PaymentInterface && $this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            $covered += (int) $payment->getAmount();
        }

        return $covered >= $order->getTotal();
    }
}
