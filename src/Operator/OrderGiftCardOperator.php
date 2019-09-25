<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardManager;

    public function __construct(
        GiftCardRepositoryInterface $giftCardCodeRepository,
        EntityManagerInterface $giftCardManager
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardManager = $giftCardManager;
    }

    /**
     * Calls when Order this GiftCardCode was bought at
     * become cancelled
     */
    public function cancel(OrderInterface $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            /** @var OrderItemUnitInterface $orderItemUnit */
            foreach ($orderItem->getUnits() as $orderItemUnit) {
                $giftCard = $this->giftCardCodeRepository->findOneByOrderItemUnit($orderItemUnit);
                if (null === $giftCard) {
                    continue;
                }

                $giftCard->disable();
            }
        }

        $this->giftCardManager->flush();
    }
}
