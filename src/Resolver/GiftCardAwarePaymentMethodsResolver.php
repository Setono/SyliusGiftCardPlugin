<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface as BasePaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

/**
 * Hides the gift card payment method from the checkout payment method choices so customers cannot select it
 * as a way to pay the remaining balance
 */
final class GiftCardAwarePaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public function __construct(
        private readonly PaymentMethodsResolverInterface $decorated,
        private readonly string $paymentMethodCode,
    ) {
    }

    public function getSupportedMethods(PaymentInterface $subject): array
    {
        return array_values(array_filter(
            $this->decorated->getSupportedMethods($subject),
            fn (BasePaymentMethodInterface $method): bool => !$this->isGiftCardMethod($method),
        ));
    }

    public function supports(PaymentInterface $subject): bool
    {
        return $this->decorated->supports($subject);
    }

    private function isGiftCardMethod(BasePaymentMethodInterface $method): bool
    {
        return $method instanceof PaymentMethodInterface && $method->getCode() === $this->paymentMethodCode;
    }
}
