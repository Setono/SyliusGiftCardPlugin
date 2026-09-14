<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Limits a gift card message to the configured setono_sylius_gift_card.purchase.maximum_message_length, so the shop
 * form and the admin form share one limit that a validation mapping could not read on its own
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class GiftCardMessageLength extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card.custom_message.too_long';
}
