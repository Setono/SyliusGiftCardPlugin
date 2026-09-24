<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

/**
 * Why a gift card cannot pay for an order. The shop customer never learns which one it was: the GiftCardIsEligible
 * constraint answers all of them with the same message, so the form cannot tell a guesser that a code exists, and
 * logs the reason instead
 */
enum GiftCardIneligibilityReason: string
{
    case NotEnabled = 'not_enabled';
    case Expired = 'expired';
    case NoBalance = 'no_balance';
    case ChannelMismatch = 'channel_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
}
