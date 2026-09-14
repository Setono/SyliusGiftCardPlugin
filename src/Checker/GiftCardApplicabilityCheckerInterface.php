<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardApplicabilityCheckerInterface
{
    /**
     * Why the gift card cannot pay for the order, or null when it can. The same rules decide whether a card may be
     * applied to a cart and whether a card already applied may stay on it as checkout completes, so the validator,
     * the applicator and the checkout guard all ask here rather than each keeping a copy of them.
     *
     * Without an order only the card itself is judged: whether it is enabled, unexpired and holds a balance
     */
    public function getInapplicabilityReason(GiftCardInterface $giftCard, ?OrderInterface $order = null): ?GiftCardInapplicabilityReason;
}
