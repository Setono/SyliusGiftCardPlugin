<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;

interface PdfRenderingOptionsProviderInterface
{
    public const ORIENTATION_PORTRAIT = 'Portrait';

    public const ORIENTATION_LANDSCAPE = 'Landscape';

    public const AVAILABLE_ORIENTATIONS = [
        self::ORIENTATION_PORTRAIT,
        self::ORIENTATION_LANDSCAPE,
    ];

    public const PAGE_SIZE_A0 = 'A0';

    public const PAGE_SIZE_A1 = 'A1';

    public const PAGE_SIZE_A2 = 'A2';

    public const PAGE_SIZE_A3 = 'A3';

    public const PAGE_SIZE_A4 = 'A4';

    public const PAGE_SIZE_A5 = 'A5';

    public const PAGE_SIZE_A6 = 'A6';

    public const PAGE_SIZE_A7 = 'A7';

    public const PAGE_SIZE_A8 = 'A8';

    public const PAGE_SIZE_A9 = 'A9';

    public const PAGE_SIZE_B0 = 'B0';

    public const PAGE_SIZE_B1 = 'B1';

    public const PAGE_SIZE_B2 = 'B2';

    public const PAGE_SIZE_B3 = 'B3';

    public const PAGE_SIZE_B4 = 'B4';

    public const PAGE_SIZE_B5 = 'B5';

    public const PAGE_SIZE_B6 = 'B6';

    public const PAGE_SIZE_B7 = 'B7';

    public const PAGE_SIZE_B8 = 'B8';

    public const PAGE_SIZE_B9 = 'B9';

    public const PAGE_SIZE_B10 = 'B10';

    public const PAGE_SIZE_C5E = 'C5E';

    public const PAGE_SIZE_COMM_10_E = 'Comm10E';

    public const PAGE_SIZE_DLE = 'DLE';

    public const PAGE_SIZE_EXECUTIVE = 'Executive';

    public const PAGE_SIZE_FOLIO = 'Folio';

    public const PAGE_SIZE_LEDGER = 'Ledger';

    public const PAGE_SIZE_LEGAL = 'Legal';

    public const PAGE_SIZE_LETTER = 'Letter';

    public const PAGE_SIZE_TABLOID = 'Tabloid';

    public const AVAILABLE_PAGE_SIZES = [
        self::PAGE_SIZE_A0,
        self::PAGE_SIZE_A1,
        self::PAGE_SIZE_A2,
        self::PAGE_SIZE_A3,
        self::PAGE_SIZE_A4,
        self::PAGE_SIZE_A5,
        self::PAGE_SIZE_A6,
        self::PAGE_SIZE_A7,
        self::PAGE_SIZE_A8,
        self::PAGE_SIZE_A9,
        self::PAGE_SIZE_B0,
        self::PAGE_SIZE_B1,
        self::PAGE_SIZE_B2,
        self::PAGE_SIZE_B3,
        self::PAGE_SIZE_B4,
        self::PAGE_SIZE_B5,
        self::PAGE_SIZE_B6,
        self::PAGE_SIZE_B7,
        self::PAGE_SIZE_B8,
        self::PAGE_SIZE_B9,
        self::PAGE_SIZE_B10,
        self::PAGE_SIZE_C5E,
        self::PAGE_SIZE_COMM_10_E,
        self::PAGE_SIZE_DLE,
        self::PAGE_SIZE_EXECUTIVE,
        self::PAGE_SIZE_FOLIO,
        self::PAGE_SIZE_LEDGER,
        self::PAGE_SIZE_LEGAL,
        self::PAGE_SIZE_LETTER,
        self::PAGE_SIZE_TABLOID,
    ];

    public const PREFERRED_PAGE_SIZES = [
        self::PAGE_SIZE_A4,
        self::PAGE_SIZE_A5,
        self::PAGE_SIZE_A6,
    ];

    public function getRenderingOptions(GiftCardConfigurationInterface $giftCardConfiguration): array;
}
