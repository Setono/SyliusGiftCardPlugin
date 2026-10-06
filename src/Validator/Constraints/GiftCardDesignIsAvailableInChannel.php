<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Holds the design of a card being issued to the designs its channel offers. The channel is chosen on the same form
 * as the design, so the form cannot narrow the choices to it, and a property constraint could not see the channel
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardDesignIsAvailableInChannel extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card.design.not_available_in_channel';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
