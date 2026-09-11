<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use function sprintf;

final class UnexpectedTypeException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param object|mixed $value
     */
    public function __construct($value, string ...$expectedTypes)
    {
        parent::__construct(sprintf('Expected argument of type "%s", "%s" given', implode(', ', $expectedTypes), get_debug_type($value)));
    }
}
