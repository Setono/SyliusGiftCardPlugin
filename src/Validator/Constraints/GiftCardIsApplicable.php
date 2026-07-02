<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class GiftCardIsApplicable extends Constraint
{
    public string $notEnabledMessage = 'setono_sylius_gift_card.gift_card.not_enabled';

    public string $expiredMessage = 'setono_sylius_gift_card.gift_card.expired';

    public string $emptyMessage = 'setono_sylius_gift_card.gift_card.no_balance';

    public string $channelMismatchMessage = 'setono_sylius_gift_card.gift_card.channel_mismatch';

    public string $currencyMismatchMessage = 'setono_sylius_gift_card.gift_card.currency_mismatch';

    public string $alreadyAppliedMessage = 'setono_sylius_gift_card.gift_card.already_applied';

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
