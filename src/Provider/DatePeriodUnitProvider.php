<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class DatePeriodUnitProvider implements DatePeriodUnitProviderInterface
{
    #[\Override]
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
