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

    /**
     * The code length is configurable and an admin can type any code, so the grouping cannot assume 16 characters
     *
     * @test
     *
     * @dataProvider codesOfVaryingLength
     */
    public function it_groups_a_code_of_any_length(string $code, string $expected): void
    {
        self::assertSame($expected, (new GiftCardCodeNormalizer())->format($code));
    }

    /** @return iterable<string, array{string, string}> */
    public static function codesOfVaryingLength(): iterable
    {
        yield '8 characters' => ['SUMMER26', 'SUMM-ER26'];
        yield '15 characters' => ['E2EREDEMPTION01', 'E2ER-EDEM-PTIO-N01'];
        yield '16 characters' => ['ABCDEFGHIJKLMNOP', 'ABCD-EFGH-IJKL-MNOP'];
        yield '20 characters' => ['ABCDEFGHIJKLMNOPQRST', 'ABCD-EFGH-IJKL-MNOP-QRST'];
    }

    /** @test */
    public function it_leaves_a_code_no_longer_than_one_group_alone(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        self::assertSame('AB', $normalizer->format('AB'));
        self::assertSame('ABCD', $normalizer->format('ABCD'));
        self::assertSame('', $normalizer->format(''));
    }

    /** @test */
    public function it_does_not_group_when_the_group_size_is_below_one(): void
    {
        self::assertSame('ABCDEFGH', (new GiftCardCodeNormalizer())->format('ABCDEFGH', 0));
    }

    /** @test */
    public function it_never_cuts_a_multibyte_character_in_two(): void
    {
        self::assertSame('ÆØÅA-BCDE', (new GiftCardCodeNormalizer())->format('ÆØÅABCDE'));
    }

    /** @test */
    public function normalizing_a_formatted_code_returns_the_original(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        foreach (['SUMMER26', 'E2EREDEMPTION01', 'ABCDEFGHIJKLMNOP', 'ABCDEFGHIJKLMNOPQRST'] as $code) {
            self::assertSame($code, $normalizer->normalize($normalizer->format($code)));
        }
    }

    /** @test */
    public function it_masks_all_but_the_last_four_characters_of_a_code(): void
    {
        self::assertSame('************MNOP', (new GiftCardCodeNormalizer())->mask('ABCDEFGHIJKLMNOP'));
    }

    /**
     * What is masked is the code as it is stored, so a code typed the way the card prints it masks the same
     *
     * @test
     */
    public function it_masks_the_canonical_code(): void
    {
        self::assertSame('************MNOP', (new GiftCardCodeNormalizer())->mask(' abcd-efgh-ijkl-mnop '));
    }

    /**
     * @test
     *
     * @dataProvider shortCodes
     */
    public function it_never_shows_more_than_a_third_of_a_code(string $code, string $expected): void
    {
        self::assertSame($expected, (new GiftCardCodeNormalizer())->mask($code));
    }

    /** @return iterable<string, array{string, string}> */
    public static function shortCodes(): iterable
    {
        yield 'the shortest code the generator makes' => ['ABCDEFGHIJKL', '********IJKL'];
        yield 'a short code an admin typed' => ['SUMMER26', '******26'];
        yield 'a stub' => ['AB', '**'];
        yield 'nothing' => ['', ''];
    }

    /**
     * A submitted code ends up in the log masked, so the guesser who typed it must not be able to forge log lines
     * of their own with control characters, or flood the log with a huge submission
     *
     * @test
     */
    public function it_keeps_a_masked_submission_to_a_single_bounded_line(): void
    {
        $normalizer = new GiftCardCodeNormalizer();

        self::assertSame('************MNOP', $normalizer->mask("ABCD\nEFGH\r\nIJKL\x1bMNOP"));

        $masked = $normalizer->mask(str_repeat('A', 100000) . 'WXYZ');
        self::assertSame(255, strlen($masked));
        self::assertStringEndsWith('*WXYZ', $masked);
    }
}
