<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * A design has to be chosen whenever the channel offers any. A channel without designs shows no picker and the card
 * renders its framed default, so there a missing design is fine, which is why a plain NotBlank would not do
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class GiftCardDesignRequired extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card_information.design.required';
}
