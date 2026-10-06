<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Definition;

use Symfony\Component\Config\Definition\IntegerNode;

/**
 * An integer node that also takes null, for an option where null means "no limit". Symfony's integer node refuses an
 * explicit null, and a scalar node with a validate() rule would refuse %env(int:...)%: Symfony checks an environment
 * variable against a dummy value while it compiles the container (0 for an int), and only a numeric node knows to leave
 * its minimum and maximum to the value the variable has at runtime
 *
 * @internal
 */
final class NullableIntegerNode extends IntegerNode
{
    protected function validateType(mixed $value): void
    {
        if (null !== $value) {
            parent::validateType($value);
        }
    }

    protected function finalizeValue(mixed $value): mixed
    {
        // The minimum and maximum bound a number, and null stands for no number at all
        return null === $value ? null : parent::finalizeValue($value);
    }
}
