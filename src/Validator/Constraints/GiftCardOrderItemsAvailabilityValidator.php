<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Sylius checks an order's stock line by line (InStock on every order item, on the cart page, in the checkout's steps
 * and when the order is placed), as lines of the same variant merge. Gift card lines never merge, each one carrying
 * cards of its own, so the cards of a tracked gift card variant were never checked together, and an order holding
 * more of them than were in stock was placed and then failed in Sylius' inventory operator when it was paid. This
 * checks every tracked gift card variant against all of its lines.
 *
 * The violation belongs to the order rather than to a line, as no line is at fault on its own. A variant one of whose
 * lines is out of stock on its own is left to Sylius' InStock, which reports that line, so a lack of stock is
 * reported once
 */
final class GiftCardOrderItemsAvailabilityValidator extends ConstraintValidator
{
    public function __construct(private readonly AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardOrderItemsAvailability) {
            throw new UnexpectedTypeException($constraint, GiftCardOrderItemsAvailability::class);
        }

        if (!$value instanceof OrderInterface) {
            throw new UnexpectedValueException($value, OrderInterface::class);
        }

        $variants = [];
        $quantities = [];
        foreach ($value->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();
            if (null === $variant || !$product instanceof ProductInterface || !$product->isGiftCard()) {
                continue;
            }

            $key = spl_object_id($variant);
            $variants[$key] = $variant;
            $quantities[$key][] = $item->getQuantity();
        }

        foreach ($variants as $key => $variant) {
            if ($this->isOutOfStockOnlyTogether($variant, $quantities[$key])) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('%itemName%', (string) $variant->getInventoryName())
                    ->addViolation()
                ;
            }
        }
    }

    /**
     * @param list<int> $quantities the quantity of every line of the variant
     */
    private function isOutOfStockOnlyTogether(ProductVariantInterface $variant, array $quantities): bool
    {
        foreach ($quantities as $quantity) {
            if (!$this->availabilityChecker->isStockSufficient($variant, $quantity)) {
                return false;
            }
        }

        return !$this->availabilityChecker->isStockSufficient($variant, array_sum($quantities));
    }
}
