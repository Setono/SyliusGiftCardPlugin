<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

/**
 * What the gift card product page's live preview starts out showing, the way the add to cart form starts out: the
 * front image of the design the form preselects, and the amount the field starts at
 */
final class GiftCardPreview
{
    public function __construct(
        /** The path of the preselected design's front image, as Sylius' image uploader stored it */
        public readonly ?string $frontImagePath,
        /** The amount the field starts at, in minor units of the currency below */
        public readonly ?int $amount,
        /** The currency the amount field is in */
        public readonly ?string $currencyCode,
    ) {
    }
}
