<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use RuntimeException;
use function sprintf;

/**
 * Thrown when a gift card payment is about to be made while an administrator has disabled the payment method it would
 * be made with. The shop refuses gift cards long before that (see GiftCardPaymentMethodProviderInterface), so this is
 * the last line of defence for whatever reaches the redemption another way
 */
final class GiftCardPaymentMethodDisabledException extends RuntimeException implements ExceptionInterface
{
    public function __construct(private readonly string $paymentMethodCode)
    {
        parent::__construct(sprintf(
            'The gift card payment method "%s" is disabled, so no gift card payment can be made with it. Enable it in the admin to take gift cards again',
            $this->paymentMethodCode,
        ));
    }

    public function getPaymentMethodCode(): string
    {
        return $this->paymentMethodCode;
    }
}
