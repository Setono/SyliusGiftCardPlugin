<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

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

    public function getPaymentMethod(): PaymentMethodInterface
    {
        return $this->findPaymentMethod() ?? throw new GiftCardPaymentMethodNotFoundException($this->paymentMethodCode);
    }
}
