<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;

final class GiftCardCodeNormalizerTest extends TestCase
{
    /** @test */
    public function it_uppercases_and_strips_separators(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        self::assertSame('ABCD1234', $normalizer->normalize('abcd-1234'));
        self::assertSame('ABCD1234', $normalizer->normalize('ABCD 1234'));
        self::assertSame('ABCD1234', $normalizer->normalize(' abcd-12 34 '));
    }

    /** @test */
    public function it_formats_a_code_into_groups(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        self::assertSame('ABCD-EFGH-IJKL-MNOP', $normalizer->format('ABCDEFGHIJKLMNOP'));
        self::assertSame('ABC-DEF', $normalizer->format('ABCDEF', 3));
    }

    /** @test */
    public function normalizing_a_formatted_code_returns_the_original(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        $code = 'ABCDEFGHIJKLMNOP';

        self::assertSame($code, $normalizer->normalize($normalizer->format($code)));
    }
}
