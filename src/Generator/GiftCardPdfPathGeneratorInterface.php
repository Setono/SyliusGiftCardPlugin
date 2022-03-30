<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

interface GiftCardPdfPathGeneratorInterface
{
    public function generatePath(GiftCardConfigurationInterface $giftCardChannelConfiguration): string;
}
