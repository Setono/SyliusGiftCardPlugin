<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface PdfOrientationProviderInterface
{
    public const ORIENTATION_PORTRAIT = 'Portrait';

    public const ORIENTATION_LANDSCAPE = 'Landscape';

    public function getAvailableOrientations(): array;
}
