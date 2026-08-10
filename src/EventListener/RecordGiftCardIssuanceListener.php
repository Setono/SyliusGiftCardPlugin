<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

/**
 * Records the opening balance of a gift card created from the admin.
 *
 * Cards bought in the shop are issued when the order is paid, because their amount is only final after
 * reconciliation. A card created here holds its balance immediately, so issuance is recorded on creation.
 * Both go through the balance operator, whose recording is idempotent per card, so a card that somehow
 * reaches both paths is still only issued once.
 */
final class RecordGiftCardIssuanceListener
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
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
