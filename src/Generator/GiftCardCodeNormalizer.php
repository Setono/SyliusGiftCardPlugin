<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

final class GiftCardCodeNormalizer implements GiftCardCodeNormalizerInterface
{
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
}
