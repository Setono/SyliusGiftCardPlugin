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
     * being on its way, without its code or PDF, unless setono_sylius_gift_card.delivery.email_physical_cards is on
     *
     * @param list<GiftCardInterface> $giftCards
     */
    public function sendGiftCardsFromOrder(OrderInterface $order, array $giftCards): void;

    /**
     * Emails a single gift card to its associated customer (used for admin created gift cards and manual resends),
     * disclosing its code and PDF under the same rule as {@see self::sendGiftCardsFromOrder()}
     */
    public function sendGiftCard(GiftCardInterface $giftCard): void;
}
