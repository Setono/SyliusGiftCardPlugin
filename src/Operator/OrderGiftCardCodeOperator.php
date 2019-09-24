<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardCodeOperator implements OrderGiftCardCodeOperatorInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardEntityManager;

    public function __construct(GiftCardRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardEntityManager)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardEntityManager = $giftCardEntityManager;
    }

    /**
     * Calls when Order this GiftCardCode was bought at
     * become cancelled
     */
    public function cancel(OrderInterface $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            /** @var GiftCardInterface[] $giftCardCodes */
            $giftCardCodes = $this->giftCardCodeRepository->findBy(['orderItem' => $orderItem]);

            foreach ($giftCardCodes as $giftCardCode) {
                $giftCardCode->disable();
            }
        }

        $this->giftCardEntityManager->flush();
    }
}
