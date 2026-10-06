<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

/**
 * What a gift card is good for right now, as one word. A card has exactly one status: where more than one would apply,
 * the first of pending, disabled, spent and expired wins (see GiftCardInterface::getStatus())
 */
enum GiftCardStatus: string
{
    /**
     * Enabled, not expired and with a balance left: the only status a card can pay with (GiftCardInterface::isUsable())
     */
    case Usable = 'usable';

    /**
     * Enabled and with a balance left, but past its expiry date, so the balance can no longer be spent
     */
    case Expired = 'expired';

    /**
     * Enabled, but nothing is left on it
     */
    case Spent = 'spent';

    /**
     * Created when the gift card was put in a cart, and waiting for its order to be paid: GiftCardInterface::isPending(),
     * on an order that is not cancelled
     */
    case Pending = 'pending';

    /**
     * Disabled, by an admin or because the order that bought it was cancelled (before or after it was paid) or refunded
     * in full
     */
    case Disabled = 'disabled';
}
