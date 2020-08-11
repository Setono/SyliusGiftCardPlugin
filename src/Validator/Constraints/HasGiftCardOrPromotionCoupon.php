<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class HasGiftCardOrPromotionCoupon extends Constraint
{
    /** @var string */
    public $message = 'setono_sylius_gift_card.add_gift_card_to_order_command.gift_card.not_blank';

    public function getTargets(): array
    {
        return [
            self::CLASS_CONSTRAINT,
        ];
    }
}
