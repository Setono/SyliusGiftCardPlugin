<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class DefaultGiftCardConfigurationMustExist extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card_configuration.default_configuration_must_exist';

    public function getTargets(): array
    {
        return [self::CLASS_CONSTRAINT];
    }
}
