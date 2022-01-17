<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class GiftCardIsNotExpired extends Constraint
{
    public string $message = 'setono_sylius_gift_card.add_gift_card_to_order_command.gift_card.is_expired';
}
