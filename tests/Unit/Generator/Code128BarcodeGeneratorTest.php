<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Generator\Code128BarcodeGenerator;

final class Code128BarcodeGeneratorTest extends TestCase
{
    /**
     * The back of the PDF used to print a hardcoded array of bar widths, the same on every card, encoding
     * nothing. This is the real symbology, so the expected widths below are the reference output of an
     * independent Code 128 implementation (picqer/php-barcode-generator) for the same value:
     * start B, C, O, D, E, 1, 2, 8, the modulo 103 check character (26), stop
     *
     * @test
     */
    public function it_draws_the_code_as_a_code_128_symbol(): void
    {
        $expected = '211214' . '131321' . '133121' . '112313' . '132113' . '123221' . '223211' . '311222' . '321221' . '2331112';

        self::assertSame($expected, $this->widths((new Code128BarcodeGenerator())->generate('CODE128')));
    }

    /**
     * A gift card code is generated from the same alphabet the normalizer keeps, so every code has to draw
     *
     * @test
     */
    public function it_draws_any_normalized_gift_card_code(): void
    {
        $generator = new Code128BarcodeGenerator();

        foreach (['A', 'SUMMER26', 'PDFTEST0000000001', '0123456789', str_repeat('Z', 64)] as $code) {
            $widths = $this->widths($generator->generate($code));

            self::assertStringStartsWith('211214', $widths, sprintf('The symbol for "%s" must start with start code B', $code));
            self::assertStringEndsWith('2331112', $widths, sprintf('The symbol for "%s" must end with the stop symbol', $code));

            // start + every character + check character, 11 modules each, plus the 13 module stop symbol
            self::assertSame((strlen($code) + 2) * 11 + 13, array_sum(array_map(intval(...), str_split($widths))));
        }
    }

    /**
     * Without the quiet zone on either side a scanner cannot tell where the symbol begins, and the barcode is
     * drawn over artwork that is not guaranteed to be light, so it brings its own white background
     *
     * @test
     */
    public function it_surrounds_the_symbol_with_a_quiet_zone(): void
    {
        $svg = $this->svg((new Code128BarcodeGenerator())->generate('SUMMER26'));

        self::assertSame(1, preg_match('#<svg[^>]* width="(\d+)"#', $svg, $svgAttributes));
        self::assertSame(1, preg_match('#<rect x="0" y="0" width="(\d+)" height="\d+" fill="\#ffffff"/>#', $svg, $background));
        self::assertSame($svgAttributes[1], $background[1]);

        // ten quiet modules at two units each, on either side of the symbol
        self::assertSame(1, preg_match('#<rect x="(\d+)"[^>]*fill="\#000000"#', $svg, $firstBar));
        self::assertSame('20', $firstBar[1]);

        preg_match_all('#<rect x="(\d+)" y="0" width="(\d+)"[^>]*fill="\#000000"#', $svg, $bars, \PREG_SET_ORDER);

        $lastBarEnd = 0;
        foreach ($bars as $bar) {
            $lastBarEnd = max($lastBarEnd, (int) $bar[1] + (int) $bar[2]);
        }

        self::assertSame((int) $svgAttributes[1] - 20, $lastBarEnd);
    }

    /** @test */
    public function it_draws_nothing_for_a_value_code_set_b_cannot_express(): void
    {
        $generator = new Code128BarcodeGenerator();

        self::assertNull($generator->generate(''));
        self::assertNull($generator->generate("CODE\n128"));
        // outside printable ASCII, e.g. a code that was never normalized
        self::assertNull($generator->generate('GAVEKORTÆØÅ'));
    }

    private function svg(?string $dataUri): string
    {
        self::assertIsString($dataUri);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true);
        self::assertIsString($svg);

        return $svg;
    }

    /**
     * Reads the drawing back as the widths in modules of its elements, alternating bar and space, the way the
     * symbology defines a symbol. The quiet zone and the trailing space after the last bar are left out
     */
    private function widths(?string $dataUri): string
    {
        $svg = $this->svg($dataUri);

        preg_match_all('#<rect x="(\d+)" y="0" width="(\d+)" height="\d+" fill="\#000000"/>#', $svg, $bars, \PREG_SET_ORDER);

        $widths = '';
        $cursor = null;
        foreach ($bars as $bar) {
            $x = (int) $bar[1];
            $width = (int) $bar[2];

            if (null !== $cursor && $x > $cursor) {
                $widths .= (string) (($x - $cursor) / 2);
            }

            $widths .= (string) ($width / 2);
            $cursor = $x + $width;
        }

        return $widths;
    }
}
