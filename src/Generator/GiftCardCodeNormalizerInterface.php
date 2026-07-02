<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

interface GiftCardCodeNormalizerInterface
{
    /**
     * Normalizes a user supplied gift card code into its canonical, stored form:
     * uppercased and stripped of any separators (dashes, spaces) so a code that is displayed
     * grouped (e.g. "ABCD-EFGH") matches what was typed
     */
    public function normalize(string $code): string;

    /**
     * Formats a canonical code for display by inserting a separator every $groupSize characters
     */
    public function format(string $code, int $groupSize = 4, string $separator = '-'): string;
}
