<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Mailer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardEmailManagerInterface
{
    /**
     * Emails all gift cards bought on the order to the order's customer. A virtual gift card is delivered by the
     * email itself: its code is in the body and the card is attached as a PDF. A physical one is only announced as
     * going to be shipped, without its code or PDF, unless setono_sylius_gift_card.delivery.email_physical_cards is on
     *
     * @param list<GiftCardInterface> $giftCards
     */
    public function sendGiftCardsFromOrder(OrderInterface $order, array $giftCards): void;

    /**
     * Emails a single gift card to its associated customer (used for admin created gift cards and manual resends)
     * with its code in the body and the card attached as a PDF. Unlike {@see self::sendGiftCardsFromOrder()} this
     * discloses the code of a physical gift card too: it is only called because an admin explicitly asked for the
     * card to be emailed, which is how a physical card the customer lost or never received is replaced
     */
    public function sendGiftCard(GiftCardInterface $giftCard): void;
}
