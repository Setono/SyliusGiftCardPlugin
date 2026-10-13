<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

final class VirtualGiftCardOrderPaymentCapturer implements VirtualGiftCardOrderPaymentCapturerInterface
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly bool $enabled,
    ) {
    }

    public function capture(PaymentInterface $payment): void
    {
        if (!$this->enabled || PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
            return;
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface || !self::buysOnlyVirtualGiftCards($order)) {
            return;
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        }
    }

    /**
     * Whether every item of the order is a gift card that is delivered by email: a physical card is shipped, and is
     * charged when it ships, like whatever else the order holds
     */
    private static function buysOnlyVirtualGiftCards(OrderInterface $order): bool
    {
        if ($order->getItems()->isEmpty()) {
            return false;
        }

        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();

            if (null === $variant || !$product instanceof ProductInterface || !$product->isGiftCard() || $variant->isShippingRequired()) {
                return false;
            }
        }

        return true;
    }
}
