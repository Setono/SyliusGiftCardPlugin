<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class GiftCardEmailManager implements GiftCardEmailManagerInterface
{
    /** @var SenderInterface */
    private $sender;

    /** @var LocaleAwareInterface */
    private $translator;

    public function __construct(SenderInterface $sender, LocaleAwareInterface $translator)
    {
        $this->sender = $sender;
        $this->translator = $translator;
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

        $channel = $order->getChannel();
        if (null === $channel) {
            return;
        }

        $defaultLocale = $channel->getDefaultLocale();
        if (null === $defaultLocale) {
            return;
        }

        $oldLocale = $this->translator->getLocale();
        $this->translator->setLocale((string) $defaultLocale->getCode());

        $this->sender->send(
            Emails::GIFT_CARD_ORDER,
            [$email],
            ['giftCards' => $giftCards, 'order' => $order, 'channel' => $channel]
        );

        $this->translator->setLocale($oldLocale);
    }
}
