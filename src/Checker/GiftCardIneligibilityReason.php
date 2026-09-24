<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

/**
 * Why a gift card cannot pay for an order. The cases mirror the messages the GiftCardIsEligible constraint renders
 */
enum GiftCardIneligibilityReason: string
{
    case NotEnabled = 'not_enabled';
    case Expired = 'expired';
    case NoBalance = 'no_balance';
    case ChannelMismatch = 'channel_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
}
