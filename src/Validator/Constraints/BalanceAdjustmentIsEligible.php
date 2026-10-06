<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class BalanceAdjustmentIsEligible extends Constraint
{
    public string $negativeBalanceMessage = 'setono_sylius_gift_card.gift_card.adjustment_makes_balance_negative';

    public string $tooLargeBalanceMessage = 'setono_sylius_gift_card.gift_card.adjustment_makes_balance_too_large';

    /** The most the balance may hold after the adjustment, in minor units, or null for no ceiling */
    public ?int $maximumBalance = null;

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
