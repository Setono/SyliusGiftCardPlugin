<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Records the opening balance of a gift card created from the admin.
 *
 * Cards bought in the shop are issued when the order is paid, because their amount is only final after
 * reconciliation. A card created here holds its balance immediately, so issuance is recorded on creation.
 * Both go through the balance operator, whose recording is idempotent per card, so a card that somehow
 * reaches both paths is still only issued once.
 *
 * It is recorded before the card is saved rather than after: Sylius' resource controller saves (and flushes) the new
 * card between the pre and the post create event, so the ledger row is written with the card, in the same flush.
 *
 * Issuing a card from the admin hands out money as much as adjusting a balance does, so the ledger row names the
 * administrator who did it, by their user identifier
 */
final class RecordGiftCardIssuanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
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

        // No order paid for a card issued here
        $this->balanceOperator->issue($giftCard, null, $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier());
    }
}
