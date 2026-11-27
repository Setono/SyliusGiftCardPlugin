<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardProductPageRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GiftCardProductPageExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setono_gift_card_preview', [GiftCardProductPageRuntime::class, 'getPreview']),
        ];
    }
}
