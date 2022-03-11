<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use function sprintf;

final class PromotionCouponNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    private string $couponCode;

    public function __construct(string $couponCode)
    {
        $this->couponCode = $couponCode;
        $message = sprintf('The coupon with code "%s" was not found', $couponCode);

        parent::__construct($message);
    }

    public function getCouponCode(): string
    {
        return $this->couponCode;
    }
}
