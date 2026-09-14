<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Calculator;

use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class GiftCardPaidAmountCalculator implements GiftCardPaidAmountCalculatorInterface
{
    public function __construct(
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
    ) {
    }

    public function getPaidAmount(OrderInterface $order): int
    {
        $paid = 0;

        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface || !$this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            if (PaymentInterface::STATE_COMPLETED !== $payment->getState()) {
                continue;
            }

            $paid += $payment->getAmount() ?? 0;
        }

        return $paid;
    }
}
