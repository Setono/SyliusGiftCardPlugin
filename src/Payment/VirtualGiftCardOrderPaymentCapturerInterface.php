<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Sylius\Component\Core\Model\PaymentInterface;

/**
 * A shop whose payment provider only authorizes at checkout and captures later, when the order ships, would keep the
 * buyer of a virtual gift card waiting for a capture that nothing triggers: a virtual card has no shipment. A digital
 * product is delivered at once, so it may be charged at once
 */
interface VirtualGiftCardOrderPaymentCapturerInterface
{
    /**
     * Called after a payment is authorized. When delivery.capture_virtual_orders is on and every item of the payment's
     * order is a gift card on a variant that requires no shipping, completes the payment, which a payment plugin that
     * captures on completion (setono/sylius-quickpay-plugin, say) captures at the gateway. Completing it pays the order,
     * and paying the order issues and emails its cards as always. The cards are never issued on the authorization
     * itself: an authorization is a hold that can lapse or be cancelled, while a code that has been sent can be spent
     */
    public function capture(PaymentInterface $payment): void;
}
