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

        // A gift card stays valid through the end of its expiry day
        return (new \DateTimeImmutable('+' . $this->defaultValidityPeriod))->setTime(23, 59, 59);
    }
}
