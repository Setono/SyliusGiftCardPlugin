<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Mailer;

final class Emails
{
    public const GIFT_CARD = 'setono_sylius_gift_card__gift_card';

    public const GIFT_CARDS_FROM_ORDER = 'setono_sylius_gift_card__gift_cards_from_order';

    private function __construct()
    {
    }
}
