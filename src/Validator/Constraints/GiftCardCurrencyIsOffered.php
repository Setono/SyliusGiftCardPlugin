<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * The currency a gift card is bought in, which the add to cart form submits along with the amount, has to be one the
 * channel being shopped in sells gift cards in
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class GiftCardCurrencyIsOffered extends Constraint
{
    public string $message = 'setono_sylius_gift_card.gift_card_information.currency_code.not_offered';
}
