<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardCodeRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly GiftCardCodeNormalizerInterface $codeNormalizer)
    {
    }

    /**
     * Groups a code for reading (ABCDEFGHIJKLMNOP as ABCD-EFGH-IJKL-MNOP). The grouping is the normalizer's, so a
     * displayed code round-trips through normalize() to the stored one whatever its length
     */
    public function format(?string $code, int $groupSize = 4, string $separator = '-'): string
    {
        if (null === $code) {
            return '';
        }

        return $this->codeNormalizer->format($code, $groupSize, $separator);
    }
}
