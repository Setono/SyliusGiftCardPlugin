<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

final class GiftCardCodeNormalizer implements GiftCardCodeNormalizerInterface
{
    /**
     * No gift card code is longer than this (the code column and the code_length option both stop here), so
     * anything longer is not a code, and masking only its end keeps a huge submission from flooding the log
     */
    private const MAXIMUM_CODE_LENGTH = 255;

    public function normalize(string $code): string
    {
        $code = mb_strtoupper($code);

        return (string) preg_replace('/[^A-Z0-9]/', '', $code);
    }

    public function format(string $code, int $groupSize = 4, string $separator = '-'): string
    {
        if ($groupSize < 1) {
            return $code;
        }

        // mb_str_split rather than str_split: a code shorter than one group comes back as that single group, an
        // empty code as nothing, and a multibyte character is never cut in two, which would hand Twig invalid UTF-8
        return implode($separator, mb_str_split($code, $groupSize));
    }

    public function mask(string $code): string
    {
        // Normalized first, which also means a submitted value cannot carry control characters into the log line,
        // and leaves nothing but ASCII letters and digits, so the byte functions below are safe
        $code = substr($this->normalize($code), -self::MAXIMUM_CODE_LENGTH);
        $length = strlen($code);

        // The last four, but never more than a third of the code: an admin can type a short code of their own,
        // and a mistyped one can be shorter still, so a fixed four could give most or all of it away
        $visible = min(4, intdiv($length, 3));

        return str_repeat('*', $length - $visible) . substr($code, $length - $visible);
    }
}
