<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Sylius\Component\Core\Model\PaymentInterface;

final class GiftCardPaymentChecker implements GiftCardPaymentCheckerInterface
{
    public function __construct(
        private readonly string $paymentMethodCode,
    ) {
    }

    public function isGiftCardPayment(PaymentInterface $payment): bool
    {
        return $payment->getMethod()?->getCode() === $this->paymentMethodCode;
    }
}
