<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses an order whose gift card lines of a tracked variant together hold more than is in stock. Validates the order
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardOrderItemsAvailability extends Constraint
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
