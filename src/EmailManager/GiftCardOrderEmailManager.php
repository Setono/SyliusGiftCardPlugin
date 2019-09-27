<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class GiftCardOrderEmailManager implements GiftCardOrderEmailManagerInterface
{
    /** @var SenderInterface */
    private $sender;

    public function __construct(SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function sendEmailWithGiftCardCodes(OrderInterface $order, array $giftCards): void
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
            self::EMAIL_CONFIG_NAME,
            [$email],
            ['giftCards' => $giftCards, 'order' => $order]
        );
    }
}
