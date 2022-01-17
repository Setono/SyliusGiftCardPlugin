<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Provider\PdfOrientationProvider;

final class PdfOrientationProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_provides_orientation_list(): void
    {
        $provider = new PdfOrientationProvider();

        $this->assertIsArray($provider->getAvailableOrientations());
    }
}
