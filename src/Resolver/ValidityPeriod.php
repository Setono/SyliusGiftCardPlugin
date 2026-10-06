<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

/**
 * The rule default_validity_period is held to, kept in one place: the extension applies it to a period written in the
 * configuration while the container compiles, and GiftCardExpiryResolver to one taken from an environment variable,
 * whose value is only known at runtime, when it resolves an expiry
 *
 * @internal
 */
final class ValidityPeriod
{
    private function __construct()
    {
    }

    /**
     * Whether the period is an interval strtotime() can add to a date, such as "3 years"
     */
    public static function isInterval(string $period): bool
    {
        return false !== strtotime('+' . $period);
    }
}
