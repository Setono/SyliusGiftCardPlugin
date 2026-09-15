<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardSetupRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GiftCardSetupExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setono_gift_card_channels_without_design', [GiftCardSetupRuntime::class, 'getChannelsWithoutDesign']),
        ];
    }
}
