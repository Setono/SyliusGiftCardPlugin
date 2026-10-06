<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses gift cards of a tracked variant that, together with the cart's other lines of the variant, are more than is
 * in stock. Validates the add to cart command
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardCartItemAvailability extends Constraint
{
    /**
     * Sylius' own message for a line it finds out of stock, which Sylius translates
     */
    public string $message = 'sylius.cart_item.not_available';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
