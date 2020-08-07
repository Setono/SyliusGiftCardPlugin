<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class HasBackgroundImage extends Constraint
{
    /** @var string */
    public $message = 'setono_sylius_gift_card.gift_card_configuration.background_image_required';

    public function getTargets(): array
    {
        return [self::CLASS_CONSTRAINT];
    }
}
