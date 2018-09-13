<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardCodeOperator implements OrderGiftCardCodeOperatorInterface
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardEntityManager;

    public function __construct(GiftCardCodeRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardEntityManager)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardEntityManager = $giftCardEntityManager;
    }

    public function cancel(OrderInterface $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            /** @var GiftCardCodeInterface[] $giftCardCodes */
            $giftCardCodes = $this->giftCardCodeRepository->findBy(['orderItem' => $orderItem]);

            foreach ($giftCardCodes as $giftCardCode) {
                $giftCardCode->setIsActive(false);
            }
        }

        $this->giftCardEntityManager->flush();
    }
}
