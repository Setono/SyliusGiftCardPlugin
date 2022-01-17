<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Provider\PdfPageSizeProvider;

final class PdfPageSizeProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_provides_page_size_list(): void
    {
        $provider = new PdfPageSizeProvider();

        $this->assertIsArray($provider->getAvailablePageSizes());
    }
}
