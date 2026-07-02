<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueDesignImageTypes extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card_design.images.unique_type';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
