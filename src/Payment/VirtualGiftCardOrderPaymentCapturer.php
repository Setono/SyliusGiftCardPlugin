<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        private readonly LoggerInterface $logger = new NullLogger(),
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

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            return;
        }

        try {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        } catch (\Throwable $exception) {
            // Something after the payment was completed failed, in paying the order, say. That is no failed capture,
            // and the transitions it broke off must not be saved half applied
            if (PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
                throw $exception;
            }

            // The capture failed before the payment's state changed: a payment plugin captures at the gateway in a
            // callback before the payment is completed, and throws when the provider refuses the capture or cannot be
            // reached. The provider still holds the customer's money, so the authorization this runs within has to be
            // saved: the order stays authorized, as it does without capture_virtual_orders, for the shop to capture
            // it by hand, which pays the order and issues its cards (#511)
            $this->logger->error(sprintf(
                'The authorized payment %s of order %s could not be completed, so it stays authorized and the order\'s gift cards are not issued. Complete the payment in the admin once the payment provider can capture it. %s',
                (string) $payment->getId(),
                (string) $order->getNumber(),
                $exception->getMessage(),
            ), ['exception' => $exception]);
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
