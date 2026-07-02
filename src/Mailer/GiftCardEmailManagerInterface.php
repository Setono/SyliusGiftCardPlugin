<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Mailer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardEmailManagerInterface
{
    /**
     * Emails all gift cards bought on the order to the order's customer, with each gift card rendered as a PDF
     * attachment. Sent for both virtual and physical gift cards so the buyer always has a digital backup
     *
     * @param list<GiftCardInterface> $giftCards
     */
    public function sendGiftCardsFromOrder(OrderInterface $order, array $giftCards): void;

    /**
     * Emails a single gift card to its associated customer (used for admin created gift cards and manual resends)
     */
    public function sendGiftCard(GiftCardInterface $giftCard): void;
}
