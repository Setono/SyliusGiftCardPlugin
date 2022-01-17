<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

interface PdfRenderingOptionProviderInterface
{
    public function getRenderingOptions(GiftCardConfigurationInterface $giftCardConfiguration): array;
}
