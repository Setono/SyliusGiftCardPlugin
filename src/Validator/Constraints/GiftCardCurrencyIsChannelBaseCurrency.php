<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardCurrencyIsChannelBaseCurrency extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card.currency_code.not_base_currency';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
