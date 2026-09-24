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

    /**
     * Masks a code for a log line: the canonical code with all but its last four characters replaced by asterisks,
     * e.g. "************MNOP". A code is a bearer token and logs travel (log shippers, error trackers), so a log
     * line may say which card it is about but must not hand its reader a code they can spend
     */
    public function mask(string $code): string;
}
