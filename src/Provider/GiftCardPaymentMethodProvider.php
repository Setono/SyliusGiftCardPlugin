<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodDisabledException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardPaymentMethodNotFoundException;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

final class GiftCardPaymentMethodProvider implements GiftCardPaymentMethodProviderInterface
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly string $paymentMethodCode,
    ) {
    }

    public function findPaymentMethod(): ?PaymentMethodInterface
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $this->paymentMethodCode]);

        return $paymentMethod instanceof PaymentMethodInterface ? $paymentMethod : null;
    }

    public function findEnabledPaymentMethod(): ?PaymentMethodInterface
    {
        $paymentMethod = $this->findPaymentMethod();

        return true === $paymentMethod?->isEnabled() ? $paymentMethod : null;
    }

    public function getEnabledPaymentMethod(): PaymentMethodInterface
    {
        $paymentMethod = $this->findPaymentMethod() ?? throw new GiftCardPaymentMethodNotFoundException($this->paymentMethodCode);
        if (!$paymentMethod->isEnabled()) {
            throw new GiftCardPaymentMethodDisabledException($this->paymentMethodCode);
        }

        return $paymentMethod;
    }
}
