<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardOrderEmailManagerInterface
{
    public const EMAIL_CONFIG_NAME = 'gift_card_order';

    /**
     * @param GiftCardInterface[] $giftCards
     */
    public function sendEmailWithGiftCards(OrderInterface $order, array $giftCards): void;
}
