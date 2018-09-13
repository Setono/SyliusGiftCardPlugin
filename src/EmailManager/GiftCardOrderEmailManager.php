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

    public function sendEmailWithGiftCardCodes(OrderInterface $order, array $giftCardCodes): void
    {
        $email = $order->getCustomer()->getEmail();

        $this->sender->send(
            self::EMAIL_CONFIG_NAME,
            [$email],
            ['giftCardCodes' => $giftCardCodes, 'order' => $order]
        );
    }
}
