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

        $groups = str_split($code, $groupSize);

        return implode($separator, $groups);
    }
}
