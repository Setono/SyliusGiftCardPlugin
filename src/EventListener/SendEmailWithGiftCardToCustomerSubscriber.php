<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * When a gift card is manually created in the backend, this listener will send an email
 * to the customer to notify them about this gift card
 */
final class SendEmailWithGiftCardToCustomerSubscriber implements EventSubscriberInterface
{
    private GiftCardEmailManagerInterface $giftCardEmailManager;

    public function __construct(GiftCardEmailManagerInterface $giftCardEmailManager)
    {
        $this->giftCardEmailManager = $giftCardEmailManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card.post_create' => 'postCreate',
        ];
    }

    public function postCreate(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();
        if (!$giftCard instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($giftCard, GiftCardInterface::class);
        }

        $customer = $giftCard->getCustomer();
        if (null === $customer) {
            return;
        }

        $this->giftCardEmailManager->sendEmailToCustomerWithGiftCard($customer, $giftCard);
    }
}
