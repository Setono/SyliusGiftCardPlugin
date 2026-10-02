<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Holds the code of a card being issued to the configured setono_sylius_gift_card.minimum_code_length, which a
 * validation mapping could not read on its own
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GiftCardCodeLength extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card.code.too_short';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
