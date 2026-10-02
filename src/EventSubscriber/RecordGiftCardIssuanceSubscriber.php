<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records the opening balance of a gift card created from the admin.
 *
 * Cards bought in the shop are issued when the order is paid, because their amount is only final after
 * reconciliation. A card created here holds its balance immediately, so issuance is recorded on creation.
 * Both go through the balance operator, whose recording is idempotent per card, so a card that somehow
 * reaches both paths is still only issued once.
 *
 * It is recorded before the card is saved rather than after: Sylius' resource controller saves (and flushes) the new
 * card between the pre and the post create event, so the ledger row is written with the card, in the same flush
 */
final class RecordGiftCardIssuanceSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GiftCardBalanceOperatorInterface $balanceOperator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After every other listener, so a listener that stops the creation has done so before anything is recorded
            'setono_sylius_gift_card.gift_card.pre_create' => ['recordIssuance', -1000],
        ];
    }

    public function recordIssuance(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();
        if (!$giftCard instanceof GiftCardInterface) {
            return;
        }

        $this->balanceOperator->issue($giftCard);
    }
}
