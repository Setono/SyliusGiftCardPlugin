<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Pdf;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

interface GiftCardPdfGeneratorInterface
{
    /**
     * Renders the given gift card to a PDF and returns its binary contents.
     *
     * Replace or decorate the default (dompdf based) implementation to use a different PDF engine
     */
    public function generate(GiftCardInterface $giftCard): string;
}
