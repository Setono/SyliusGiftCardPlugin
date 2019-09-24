<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;

final class GiftCardCodeDeletionListener
{
    /**
     * Prevent gift card code deletion if it not deletable
     */
    public function onGiftCardCodePreDelete(ResourceControllerEvent $event): void
    {
        $giftCardCode = $event->getSubject();

        if (!$giftCardCode instanceof GiftCardCodeInterface) {
            throw new UnexpectedTypeException(
                $giftCardCode,
                GiftCardCodeInterface::class
            );
        }

        if (!$giftCardCode->isDeletable()) {
            $event->stop('setono_sylius_gift_card.gift_card_code.delete_error');
        }
    }
}
