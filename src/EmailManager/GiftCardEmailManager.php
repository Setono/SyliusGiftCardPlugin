<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class GiftCardEmailManager implements GiftCardEmailManagerInterface
{
    /** @var SenderInterface */
    private $sender;

    public function __construct(SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function sendEmailToCustomerWithGiftCard(CustomerInterface $customer, GiftCardInterface $giftCard): void
    {
        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $this->sender->send(
            Emails::GIFT_CARD_CUSTOMER,
            [$email],
            ['customer' => $customer, 'giftCard' => $giftCard]
        );
    }

    public function sendEmailWithGiftCardsFromOrder(OrderInterface $order, array $giftCards): void
    {
        $customer = $order->getCustomer();
        if (null === $customer) {
            return;
        }

        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $this->sender->send(
            Emails::GIFT_CARD_ORDER,
            [$email],
            ['giftCards' => $giftCards, 'order' => $order]
        );
    }
}
