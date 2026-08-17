<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardRedemptionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GiftCardRedemptionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setono_gift_card_apply_form', [GiftCardRedemptionRuntime::class, 'createApplyForm']),
            new TwigFunction('setono_gift_card_covered_amount', [GiftCardRedemptionRuntime::class, 'getCoveredAmount']),
            new TwigFunction('setono_gift_card_covered_amount_by_gift_card', [GiftCardRedemptionRuntime::class, 'getCoveredAmountByGiftCard']),
            new TwigFunction('setono_gift_card_remaining_total', [GiftCardRedemptionRuntime::class, 'getRemainingTotal']),
        ];
    }
}
