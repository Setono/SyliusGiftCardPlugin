<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface DefaultPdfCssProviderInterface
{
    public function getDefaultCss(): string;
}
