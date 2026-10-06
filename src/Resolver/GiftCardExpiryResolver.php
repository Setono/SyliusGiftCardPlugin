<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

final class GiftCardExpiryResolver implements GiftCardExpiryResolverInterface
{
    /**
     * @param string|null $defaultValidityPeriod a strtotime compatible interval, e.g. "3 years", or null to never expire
     */
    public function __construct(private readonly ?string $defaultValidityPeriod)
    {
    }

    public function resolve(): ?\DateTimeImmutable
    {
        if (null === $this->defaultValidityPeriod) {
            return null;
        }

        // The extension refuses a period that is no interval, but cannot see the value of one taken from an environment
        // variable, which is only known at runtime. That is checked here, by the same rule, and not when the resolver is
        // built, since the add to cart form and the order state machine callbacks build it on every product page and
        // for every order, whether a gift card is issued or not
        if (!ValidityPeriod::isInterval($this->defaultValidityPeriod)) {
            throw new \InvalidArgumentException(sprintf(
                'The default_validity_period must be a valid strtotime interval, e.g. "3 years": "%s"',
                $this->defaultValidityPeriod,
            ));
        }

        // A gift card stays valid through the end of its expiry day
        return (new \DateTimeImmutable('+' . $this->defaultValidityPeriod))->setTime(23, 59, 59);
    }
}
