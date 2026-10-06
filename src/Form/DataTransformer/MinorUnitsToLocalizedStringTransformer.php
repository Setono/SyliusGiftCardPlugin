<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\MoneyToLocalizedStringTransformer;

/**
 * Turns an amount typed into a money field into an integer of minor units, like Sylius' own money transformer does,
 * except for an amount whose minor units are beyond PHP's integer range. Sylius' transformer casts that float to an int,
 * which PHP wraps around to an arbitrary integer, possibly a small positive one that every constraint accepts:
 * 184467440737095560 typed into the field used to arrive as 4096. Symfony's number transformer underneath refuses a
 * number beyond the range the same way, but only before the divisor has made it a hundred times larger
 */
final class MinorUnitsToLocalizedStringTransformer extends MoneyToLocalizedStringTransformer
{
    /**
     * @param string|null $value
     */
    public function reverseTransform(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        $value = parent::reverseTransform($value);
        if (null === $value) {
            return null;
        }

        $minorUnits = round($value);

        // (float) PHP_INT_MAX is 2^63, one more than the largest integer, so a float equal to it is out of range as well
        if ($minorUnits >= \PHP_INT_MAX || $minorUnits < \PHP_INT_MIN) {
            throw new TransformationFailedException(sprintf('The amount %s is beyond the range of an integer in minor units.', (string) $value));
        }

        return (int) $minorUnits;
    }
}
