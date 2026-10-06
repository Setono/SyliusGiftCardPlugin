<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Sylius checks the stock of what is added against one line of the cart: the line it merges with, as lines of the same
 * variant merge (CartItemAvailability), or the new line on its own once the form is valid (InStock). Gift card lines
 * never merge, each one carrying cards of its own, so for a tracked gift card variant neither check counts the cards
 * the cart already holds in its other lines. This counts them.
 *
 * What a single line can check is left to Sylius: nothing is reported when the new line is out of stock on its own,
 * or while the cart holds no other line of the variant, so a lack of stock is reported once
 */
final class GiftCardCartItemAvailabilityValidator extends ConstraintValidator
{
    public function __construct(private readonly AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCartItemAvailability) {
            throw new UnexpectedTypeException($constraint, GiftCardCartItemAvailability::class);
        }

        if (!$value instanceof AddToCartCommandInterface) {
            throw new UnexpectedValueException($value, AddToCartCommandInterface::class);
        }

        $cartItem = $value->getCartItem();
        $variant = $cartItem->getVariant();
        $product = $variant?->getProduct();
        if (null === $variant || !$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        $quantityInOtherLines = 0;
        foreach ($value->getCart()->getItems() as $item) {
            if ($item !== $cartItem && $item->getVariant() === $variant) {
                $quantityInOtherLines += $item->getQuantity();
            }
        }

        if (!$this->availabilityChecker->isStockSufficient($variant, $cartItem->getQuantity())) {
            return;
        }

        if ($this->availabilityChecker->isStockSufficient($variant, $cartItem->getQuantity() + $quantityInOtherLines)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%itemName%', (string) $variant->getInventoryName())
            ->addViolation()
        ;
    }
}
