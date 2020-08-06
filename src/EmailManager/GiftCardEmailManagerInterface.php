<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface GiftCardEmailManagerInterface
{
    public function sendEmailToCustomerWithGiftCard(CustomerInterface $customer, GiftCardInterface $giftCard): void;

    /**
     * @param GiftCardInterface[] $giftCards
     */
    public function sendEmailWithGiftCardsFromOrder(OrderInterface $order, array $giftCards): void;
}
