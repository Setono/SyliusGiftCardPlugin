<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\DataTransformer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Form\DataTransformer\MinorUnitsToLocalizedStringTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Money is typed in major units and kept in minor units. The minor units have to be an integer, and an amount whose
 * minor units PHP cannot hold as one must not come out as some other integer
 */
final class MinorUnitsToLocalizedStringTransformerTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider amounts
     */
    public function it_turns_a_typed_amount_into_minor_units(string $typed, int $minorUnits): void
    {
        self::assertSame($minorUnits, $this->transformer()->reverseTransform($typed));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function amounts(): iterable
    {
        yield 'an amount with cents' => ['12.50', 1250];
        // 0.29 * 100 is 28.999999999999996, which a cast without rounding would make 28
        yield 'cents without an exact binary representation' => ['0.29', 29];
        yield 'a deduction' => ['-12.50', -1250];
        yield 'the most a gift card holds' => ['21474836.47', 2147483647];
        yield 'far more than a gift card holds, which the constraints refuse' => ['90000000000000000', 9000000000000000000];
    }

    /** @test */
    public function it_turns_a_blank_field_into_null(): void
    {
        self::assertNull($this->transformer()->reverseTransform(''));
    }

    /**
     * Sylius' money transformer casts these to an int, and PHP wraps them around: the first used to arrive as 4096, an
     * amount of 40.96 every constraint accepts
     *
     * @test
     *
     * @dataProvider amountsBeyondTheIntegerRange
     */
    public function it_refuses_an_amount_whose_minor_units_php_cannot_hold_as_an_integer(string $typed): void
    {
        $this->expectException(TransformationFailedException::class);

        $this->transformer()->reverseTransform($typed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsBeyondTheIntegerRange(): iterable
    {
        yield 'wrapping around to 40.96' => ['184467440737095560'];
        yield 'a deduction wrapping around' => ['-184467440737095560'];
        yield 'a hundredth of the largest integer, made too large by the divisor' => ['92233720368547760'];
    }

    /** @test */
    public function it_shows_minor_units_in_major_units(): void
    {
        self::assertSame('12.50', $this->transformer()->transform(1250));
        self::assertSame('21474836.47', $this->transformer()->transform(2147483647));
        self::assertSame('', $this->transformer()->transform(null));
    }

    /**
     * Built the way MinorUnitsMoneyType builds it from the money field's defaults, in a fixed locale
     */
    private function transformer(): MinorUnitsToLocalizedStringTransformer
    {
        return new MinorUnitsToLocalizedStringTransformer(2, false, null, 100, 'en');
    }
}
