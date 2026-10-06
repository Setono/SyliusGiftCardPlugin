<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Webmozart\Assert\Assert;

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

        // The configuration refuses a period strtotime() cannot read, but cannot see the value of one taken from an
        // environment variable, which is only known at runtime. That is checked here and not when the resolver is
        // built, since the add to cart form and the order state machine callbacks build it on every product page and
        // for every order, whether a gift card is issued or not
        Assert::notFalse(strtotime('+' . $this->defaultValidityPeriod), sprintf(
            'The default_validity_period must be a valid strtotime interval, e.g. "3 years": "%s"',
            $this->defaultValidityPeriod,
        ));

        // A gift card stays valid through the end of its expiry day
        return (new \DateTimeImmutable('+' . $this->defaultValidityPeriod))->setTime(23, 59, 59);
    }
}
