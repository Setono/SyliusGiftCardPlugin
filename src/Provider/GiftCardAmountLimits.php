<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class GiftCardAmountLimits
{
    public function __construct(
        public readonly int $minimum,
        public readonly ?int $maximum,
    ) {
    }
}
