<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class CartItemVariantRequiredValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CartItemVariantRequired) {
            throw new UnexpectedTypeException($constraint, CartItemVariantRequired::class);
        }

        if (!$value instanceof AddToCartCommandInterface) {
            throw new UnexpectedValueException($value, AddToCartCommandInterface::class);
        }

        if (null !== $value->getCartItem()->getVariant()) {
            return;
        }

        // The path of the add to cart form's variant field, which sits in the line's form
        $this->context->buildViolation($constraint->message)
            ->atPath('cartItem.variant')
            ->addViolation()
        ;
    }
}
