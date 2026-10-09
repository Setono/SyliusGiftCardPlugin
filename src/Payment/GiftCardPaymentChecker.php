<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Payment;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

final class GiftCardPaymentChecker implements GiftCardPaymentCheckerInterface
{
    public function __construct(
        private readonly string $paymentMethodCode,
    ) {
    }

    public function isGiftCardPayment(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();

        return null !== $paymentMethod && $this->isGiftCardPaymentMethod($paymentMethod);
    }

    public function isGiftCardPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        return $paymentMethod->getCode() === $this->paymentMethodCode;
    }
}
