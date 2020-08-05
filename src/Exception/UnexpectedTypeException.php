<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use function get_class;
use function gettype;
use InvalidArgumentException;
use function is_object;
use function Safe\sprintf;

final class UnexpectedTypeException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param object|mixed $value
     */
    public function __construct($value, string ...$expectedTypes)
    {
        parent::__construct(sprintf('Expected argument of type "%s", "%s" given', implode(', ', $expectedTypes), is_object($value) ? get_class($value) : gettype($value)));
    }
}
