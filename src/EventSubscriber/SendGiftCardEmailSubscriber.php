<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Emails a gift card to its customer right after an admin creates it, if the admin asked for a notification
 */
final class SendGiftCardEmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GiftCardEmailManagerInterface $emailManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card.post_create' => 'onGiftCardPostCreate',
        ];
    }

    public function onGiftCardPostCreate(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();
        if (!$giftCard instanceof GiftCardInterface) {
            return;
        }

        if (null === $giftCard->getCustomer() || !$giftCard->getSendNotificationEmail()) {
            return;
        }

        $this->emailManager->sendGiftCard($giftCard);
    }
}
