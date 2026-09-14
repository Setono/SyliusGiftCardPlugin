<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Generator\BarcodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardCodeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly GiftCardCodeNormalizerInterface $codeNormalizer,
        private readonly BarcodeGeneratorInterface $barcodeGenerator,
    ) {
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

    /**
     * Returns an image data URI with a barcode of the code, ready to be used as the src of an img element, or null
     * when the code cannot be pictured. It is the normalized code that is encoded, so scanning the barcode yields
     * the stored code rather than the grouped one shown next to it
     */
    public function barcode(?string $code): ?string
    {
        if (null === $code) {
            return null;
        }

        $code = $this->codeNormalizer->normalize($code);
        if ('' === $code) {
            return null;
        }

        return $this->barcodeGenerator->generate($code);
    }
}
