<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
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
 */
final class RecordGiftCardIssuanceSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card.post_create' => 'recordIssuance',
        ];
    }

    public function recordIssuance(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();
        if (!$giftCard instanceof GiftCardInterface) {
            return;
        }

        $this->balanceOperator->issue($giftCard);

        $this->getManager($giftCard)->flush();
    }
}
