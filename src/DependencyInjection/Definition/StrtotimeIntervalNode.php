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
    protected function finalizeValue(mixed $value): mixed
    {
        $value = parent::finalizeValue($value);

        if (null === $value || $this->isHandlingPlaceholder()) {
            return $value;
        }

        if (!is_string($value) || false === strtotime('+' . $value)) {
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
