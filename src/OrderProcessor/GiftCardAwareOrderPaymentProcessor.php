<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Decorates Sylius' order payment processor: after the inner processor sizes the gateway payment to the full
 * order total, this reduces it to the amount not covered by gift cards (removing it entirely when the gift cards
 * cover everything).
 *
 * Where "covered by gift cards" comes from depends on which of Sylius' two payment processors is decorated. The
 * checkout one sizes the cart payment while the order is still a cart and the gift cards have not been charged,
 * so the coverage is computed from their live balances. The after-checkout one sizes the replacement payment
 * Sylius provides when a gateway payment fails or is cancelled on a placed order; by then the gift cards have
 * been redeemed (and may have been spent on other orders since), so the truth is in the completed gift card
 * payments on the order
 */
final class GiftCardAwareOrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(
        private readonly OrderProcessorInterface $decorated,
        private readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
        private readonly GiftCardPaidAmountCalculatorInterface $paidAmountCalculator,
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

        $covered = $this->getCoveredAmount($order);
        if ($covered <= 0) {
            return;
        }

        $remaining = max(0, $order->getTotal() - $covered);

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

    private function getCoveredAmount(OrderInterface $order): int
    {
        if (PaymentInterface::STATE_CART === $this->targetState) {
            return $this->coverageCalculator->calculate($order)->getTotal();
        }

        return $this->paidAmountCalculator->getPaidAmount($order);
    }
}
