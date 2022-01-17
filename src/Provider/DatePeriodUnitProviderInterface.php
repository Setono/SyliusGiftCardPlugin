<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface DatePeriodUnitProviderInterface
{
    public function getPeriodUnits(): array;
}
