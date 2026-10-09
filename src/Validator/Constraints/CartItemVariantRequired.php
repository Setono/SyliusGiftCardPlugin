<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * The line added to the cart has to have a variant. The add to cart form leaves it without one when a request leaves
 * out the variant choice of a product with several variants, and Sylius' stock check reads the variant without
 * checking it. Validates the add to cart command, and reports on the variant field, which is two levels down from the
 * command: a NotNull mapped on the command could only check the command itself or one of its properties
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class CartItemVariantRequired extends Constraint
{
    public string $message = 'setono_sylius_gift_card.add_to_cart_command.cart_item.variant.required';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
