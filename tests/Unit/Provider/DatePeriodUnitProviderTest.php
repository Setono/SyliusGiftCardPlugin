<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProvider;

final class DatePeriodUnitProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_provides_units_list(): void
    {
        $provider = new DatePeriodUnitProvider();

        $this->assertIsArray($provider->getPeriodUnits());
    }
}
