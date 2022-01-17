<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

class DatePeriodUnitProvider implements DatePeriodUnitProviderInterface
{
    public function getPeriodUnits(): array
    {
        return [
            'hour',
            'day',
            'week',
            'month',
            'year',
        ];
    }
}
