<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface PdfPageSizeProviderInterface
{
    public const FORMAT_A3 = 'A3';
    public const FORMAT_A4 = 'A4';
    public const FORMAT_A5 = 'A5';
    public const FORMAT_A6 = 'A6';
    public const FORMAT_A7 = 'A7';
    public const FORMAT_A8 = 'A8';

    public function getAvailablePageSizes(): array;
}
