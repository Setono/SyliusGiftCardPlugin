<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ValidGiftCardAmount extends Constraint
{
    public string $tooLowMessage = 'setono_sylius_gift_card.gift_card_information.amount.too_low';

    public string $tooHighMessage = 'setono_sylius_gift_card.gift_card_information.amount.too_high';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
