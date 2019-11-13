<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class GiftCardExists extends Constraint
{
    /** @var string */
    public $message = 'The gift card does not exist';

    public function validatedBy(): string
    {
        return 'setono_sylius_gift_card_gift_exists_validator';
    }
}
