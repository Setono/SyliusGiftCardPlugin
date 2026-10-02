<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use RuntimeException;
use function sprintf;

/**
 * Thrown when a gift card payment is about to be made while the shop has no payment method to make it with. The shop
 * refuses gift cards long before that (see GiftCardPaymentMethodProviderInterface), so this is the last line of
 * defence for whatever reaches the redemption another way
 */
final class GiftCardPaymentMethodNotFoundException extends RuntimeException implements ExceptionInterface
{
    public function __construct(private readonly string $paymentMethodCode)
    {
        parent::__construct(sprintf(
            'The gift card payment method "%s" does not exist. Create it with bin/console setono:gift-card:create-payment-method, or as an offline payment method with the code "%s" in the admin',
            $this->paymentMethodCode,
            $this->paymentMethodCode,
        ));
    }

    public function getPaymentMethodCode(): string
    {
        return $this->paymentMethodCode;
    }
}
