<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProvider;

final class DatePeriodUnitProviderTest extends TestCase
{
    #[Test]
    public function it_provides_units_list(): void
    {
        $provider = new DatePeriodUnitProvider();

        $this->assertIsArray($provider->getPeriodUnits());
    }
}
