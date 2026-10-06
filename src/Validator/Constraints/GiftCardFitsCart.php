<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Refuses to put a gift card in the cart when the cart's totals would no longer fit Sylius' integer columns, which a
 * customer chosen amount can make them do. Validates the add to cart command
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardFitsCart extends Constraint
{
    public string $message = 'setono_sylius_gift_card.add_to_cart_command.gift_card_does_not_fit_cart';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
