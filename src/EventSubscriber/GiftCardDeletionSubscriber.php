<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GiftCardDeletionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card.pre_delete' => 'onGiftCardPreDelete',
        ];
    }

    /**
     * Prevent gift card code deletion if it not deletable
     */
    public function onGiftCardPreDelete(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();

        if (!$giftCard instanceof GiftCardInterface) {
            throw new UnexpectedTypeException(
                $giftCard,
                GiftCardInterface::class
            );
        }

        if (!$giftCard->isDeletable()) {
            $event->stop('setono_sylius_gift_card.gift_card.delete_error');
        }
    }
}
