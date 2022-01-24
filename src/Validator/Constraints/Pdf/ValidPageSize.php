<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints\Pdf;

use Symfony\Component\Validator\Constraint;

final class ValidPageSize extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card_configuration.page_size_not_valid';
}
