<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

class PdfPageSizeProvider implements PdfPageSizeProviderInterface
{
    public function getAvailablePageSizes(): array
    {
        return [
            self::FORMAT_A3,
            self::FORMAT_A4,
            self::FORMAT_A5,
            self::FORMAT_A6,
            self::FORMAT_A7,
            self::FORMAT_A8,
        ];
    }
}
