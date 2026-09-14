<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class GiftCardIsApplicable extends Constraint
{
    /**
     * The one and only message a shop customer gets for every reason a gift card cannot be applied:
     * disabled, expired, empty, wrong channel or wrong currency. Telling the reasons apart would tell a
     * code guesser that the code exists, which is exactly what should stay secret. The actual reason is
     * written to the log instead
     */
    public string $message = 'setono_sylius_gift_card.gift_card.could_not_be_applied';

    /**
     * Being specific here reveals nothing: the customer entered a code that is already on their own order
     */
    public string $alreadyAppliedMessage = 'setono_sylius_gift_card.gift_card.already_applied';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
