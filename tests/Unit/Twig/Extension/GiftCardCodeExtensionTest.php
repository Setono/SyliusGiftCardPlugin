<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Twig\Extension\GiftCardCodeExtension;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;

final class GiftCardCodeExtensionTest extends TestCase
{
    /** @test */
    public function it_exposes_the_format_code_filter_backed_by_the_runtime(): void
    {
        $filters = (new GiftCardCodeExtension())->getFilters();

        self::assertCount(1, $filters);
        self::assertSame('setono_gift_card_format_code', $filters[0]->getName());
        self::assertSame([GiftCardCodeRuntime::class, 'format'], $filters[0]->getCallable());
    }
}
