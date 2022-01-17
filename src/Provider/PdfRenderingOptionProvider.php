<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

final class PdfRenderingOptionProvider implements PdfRenderingOptionProviderInterface
{
    public function getRenderingOptions(GiftCardConfigurationInterface $giftCardConfiguration): array
    {
        $options = [];
        $pageSize = $giftCardConfiguration->getPageSize();
        if (null !== $pageSize) {
            $options['page-size'] = $pageSize;
        }

        $orientation = $giftCardConfiguration->getOrientation();
        if (null !== $orientation) {
            $options['orientation'] = $orientation;
        }

        return $options;
    }
}
