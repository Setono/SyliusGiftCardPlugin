<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class DatePeriod extends Constraint
{
    public string $invalidDuration = 'setono_sylius_gift_card.gift_card_configuration.invalid_duration';

    public string $invalidUnit = 'setono_sylius_gift_card.gift_card_configuration.invalid_unit';
}
