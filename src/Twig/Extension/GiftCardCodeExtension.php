<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class GiftCardCodeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('setono_gift_card_format_code', [GiftCardCodeRuntime::class, 'format']),
            new TwigFilter('setono_gift_card_barcode', [GiftCardCodeRuntime::class, 'barcode']),
        ];
    }
}
