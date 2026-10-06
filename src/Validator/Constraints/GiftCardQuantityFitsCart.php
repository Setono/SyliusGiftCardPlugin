<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses a raised gift card quantity on the cart page when the cart's totals would no longer fit Sylius' integer
 * columns. Validates the cart
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardQuantityFitsCart extends Constraint
{
    public string $message = 'setono_sylius_gift_card.order.gift_card_quantity_does_not_fit_cart';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
