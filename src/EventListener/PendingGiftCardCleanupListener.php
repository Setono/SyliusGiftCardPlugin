<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;

/**
 * Deletes pending (never paid) gift cards when their order item unit is removed, e.g. when a customer edits
 * their cart or when Sylius prunes expired carts. Completed gift cards are never touched because the database
 * foreign key is SET NULL and this listener only removes cards that are still pending
 */
final class PendingGiftCardCleanupListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }

        $unitOfWork = $em->getUnitOfWork();

        /** @var list<GiftCardInterface> $giftCardsToRemove */
        $giftCardsToRemove = [];

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof OrderItemUnitInterface) {
                continue;
            }

            $giftCard = $entity->getGiftCard();
            if ($giftCard instanceof GiftCardInterface && $giftCard->isPending()) {
                $giftCardsToRemove[] = $giftCard;
            }
        }

        foreach ($giftCardsToRemove as $giftCard) {
            $em->remove($giftCard);
        }
    }
}
