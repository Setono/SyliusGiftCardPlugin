<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Decorates Sylius' order payment processor: after the inner processor sizes the gateway payment to the full
 * order total, this reduces it to the amount not covered by gift cards (removing it entirely when the gift cards
 * cover everything)
 */
final class GiftCardAwareOrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(
        private readonly OrderProcessorInterface $decorated,
        private readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
        private readonly string $targetState,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        $this->decorated->process($order);

        if (!$order instanceof OrderInterface) {
            return;
        }

        $coverage = $this->coverageCalculator->calculate($order)->getTotal();
        if ($coverage <= 0) {
            return;
        }

        $remaining = max(0, $order->getTotal() - $coverage);

        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface) {
                continue;
            }

            if ($payment->getState() !== $this->targetState || $this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            if ($remaining <= 0) {
                $order->removePayment($payment);
            } else {
                $payment->setAmount($remaining);
            }
        }
    }
}
