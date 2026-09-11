<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class BalanceAdjustmentIsApplicable extends Constraint
{
    public string $negativeBalanceMessage = 'setono_sylius_gift_card.gift_card.adjustment_makes_balance_negative';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
