<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * A scalar node for an interval strtotime() can add to a date, such as "3 years", or null. A validate() rule would
 * refuse %env(...)%: Symfony checks an environment variable against a dummy value while it compiles the container
 * ('' for a string), and no interval is ''. Like a numeric node with its minimum and maximum, this node leaves the
 * interval of an environment variable to the value the variable has at runtime, where whatever uses it has to check
 * it. Only the type is known while compiling, so a variable that cannot give a string is still refused
 *
 * @internal
 */
final class StrtotimeIntervalNode extends ScalarNode
{
    /**
     * The units of time an interval is counted in. 'weekdays' are working days; strtotime() reads every other spelling
     * of these units (e.g. "3 Years", "18 month", "2 fortnights") into one of them
     */
    private const UNITS = ['year', 'month', 'day', 'hour', 'minute', 'second', 'weekdays'];

    /**
     * Whether strtotime() reads the value as an interval forward in time, and as nothing else: numbers of units of
     * time, each counted forward, such as "3 years", "18 months", "90 days" or "1 year 6 months". This is the check for
     * a value written in the configuration and for one taken from an environment variable at runtime alike.
     *
     * Whether strtotime() can read it is not enough. strtotime() reads dates, times, day names and timezones as well as
     * intervals, and only fails on an error: it reads the number before a unit it does not know as a timezone offset
     * and drops the word with a warning, so "+3 yrs" (like a bare "+3") is now, in UTC+3, and a gift card given that
     * expiry expires the day it is issued
     */
    public static function isInterval(string $value): bool
    {
        $parsed = date_parse('+' . $value);
        $relative = $parsed['relative'] ?? null;

        if (0 !== $parsed['error_count'] || 0 !== $parsed['warning_count'] || true === $parsed['is_localtime'] || !is_array($relative)) {
            return false;
        }

        // A date or a time of day sets the moment instead of moving it
        foreach (['year', 'month', 'day', 'hour', 'minute', 'second'] as $field) {
            if (false !== $parsed[$field]) {
                return false;
            }
        }

        // Only units of time, so no day name ("3 mon" is the third Monday from now, not 3 months) and no first or last
        // day of a month
        if ([] !== array_diff_key($relative, array_flip(self::UNITS))) {
            return false;
        }

        // No unit may go back and one at least has to go forward: an interval of nothing, or one that goes back
        // ("-1 year", "3 years ago"), would expire every gift card the day it is issued, or before
        return [] !== $relative && min($relative) >= 0 && max($relative) > 0;
    }

    protected function finalizeValue(mixed $value): mixed
    {
        $value = parent::finalizeValue($value);

        if (null === $value || $this->isHandlingPlaceholder()) {
            return $value;
        }

        if (!is_string($value) || !self::isInterval($value)) {
            $ex = new InvalidConfigurationException(sprintf(
                'Invalid configuration for path "%s": The %s must be a valid strtotime interval, e.g. "3 years": %s',
                $this->getPath(),
                $this->getName(),
                json_encode($value),
            ));
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    protected function getValidPlaceholderTypes(): array
    {
        return ['string'];
    }
}
