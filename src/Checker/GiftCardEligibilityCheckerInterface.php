<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardEligibilityCheckerInterface
{
    /**
     * Why the gift card cannot pay for the order, or null when it can. The same rules decide whether a card may be
     * applied to a cart, how much a card already applied covers, and whether it may stay on the cart as checkout
     * completes, so the validator, the applicator, the coverage calculator and the checkout guard all ask here
     * rather than each keeping a copy of them.
     *
     * Without an order only the card itself is judged: whether it is enabled, unexpired and holds a balance
     */
    public function getIneligibilityReason(GiftCardInterface $giftCard, ?OrderInterface $order = null): ?GiftCardIneligibilityReason;
}
