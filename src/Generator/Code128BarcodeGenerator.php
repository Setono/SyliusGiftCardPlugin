<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

/**
 * Draws a scannable Code 128 (code set B) barcode as an SVG data URI.
 *
 * Code set B covers the printable ASCII range, which contains every character a normalized gift card code can
 * consist of, and needs no shift or code set switching. The symbology is implemented here rather than pulled in
 * as a dependency: it is a fixed, decades old table plus a modulo 103 check character, and the plugin would
 * otherwise force a barcode library - and its license and PHP requirement - on every host application
 */
final class Code128BarcodeGenerator implements BarcodeGeneratorInterface
{
    /**
     * The 107 Code 128 symbols, each as the widths in modules of its elements, alternating bar and space and
     * starting with a bar. Every symbol is 11 modules wide across 6 elements, except the stop symbol, which is
     * 13 modules across 7. The index is the symbol value
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /** Code set B encodes the printable ASCII characters, space (32) through tilde (126), as the values 0-94 */
    private const CODE_B_OFFSET = 32;

    /** The symbology reserves ten quiet modules on either side of the symbol, without which a scanner cannot find it */
    private const QUIET_ZONE = 10;

    /**
     * The drawing is a vector: these are the units of its own coordinate system, not pixels on the page. The
     * including document decides how large the barcode ends up by sizing the img element
     */
    private const MODULE_WIDTH = 2;

    private const HEIGHT = 60;

    public function generate(string $value): ?string
    {
        $symbols = $this->encode($value);
        if (null === $symbols) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($this->draw($symbols));
    }

    /**
     * @return non-empty-list<int>|null the symbol values to draw - start, data, check character, stop - or null if
     *                                 the value contains a character code set B cannot express
     */
    private function encode(string $value): ?array
    {
        $length = strlen($value);
        if (0 === $length) {
            return null;
        }

        $data = [];
        for ($i = 0; $i < $length; ++$i) {
            $character = ord($value[$i]);
            if ($character < self::CODE_B_OFFSET || $character > 126) {
                return null;
            }

            $data[] = $character - self::CODE_B_OFFSET;
        }

        // The check character weighs every data symbol by its one based position, the start symbol by one
        $checksum = self::START_B;
        foreach ($data as $position => $symbol) {
            $checksum += ($position + 1) * $symbol;
        }

        return [self::START_B, ...$data, $checksum % 103, self::STOP];
    }

    /**
     * @param non-empty-list<int> $symbols
     */
    private function draw(array $symbols): string
    {
        $x = self::QUIET_ZONE * self::MODULE_WIDTH;
        $bars = '';

        foreach ($symbols as $symbol) {
            $bar = true;
            foreach (str_split(self::PATTERNS[$symbol]) as $modules) {
                $width = (int) $modules * self::MODULE_WIDTH;
                if ($bar) {
                    $bars .= sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#000000"/>', $x, $width, self::HEIGHT);
                }

                $x += $width;
                $bar = !$bar;
            }
        }

        $width = $x + self::QUIET_ZONE * self::MODULE_WIDTH;

        // The white background is the quiet zone: the barcode is laid over artwork that is not guaranteed to be light
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none"><rect x="0" y="0" width="%1$d" height="%2$d" fill="#ffffff"/>%3$s</svg>',
            $width,
            self::HEIGHT,
            $bars,
        );
    }
}
