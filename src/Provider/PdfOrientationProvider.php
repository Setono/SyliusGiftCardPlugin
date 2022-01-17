<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

class PdfOrientationProvider implements PdfOrientationProviderInterface
{
    public function getAvailableOrientations(): array
    {
        return [
            self::ORIENTATION_PORTRAIT,
            self::ORIENTATION_LANDSCAPE,
        ];
    }
}
