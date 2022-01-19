<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface PdfAvailableCssOptionProviderInterface
{
    public function getAvailableOptions(): array;

    public function getOptionsValue(array $context): array;

    public function getOptionsHint(): array;
}
