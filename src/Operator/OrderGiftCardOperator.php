<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Webmozart\Assert\Assert;

/**
 * This class' responsibility is to operate on gift cards bought on an order
 * It does NOT handle gift cards used to buy an order
 */
final class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    private GiftCardRepositoryInterface $giftCardRepository;

    private EntityManagerInterface $giftCardManager;

    private GiftCardEmailManagerInterface $giftCardOrderEmailManager;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        EntityManagerInterface $giftCardManager,
        GiftCardEmailManagerInterface $giftCardOrderEmailManager
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardManager = $giftCardManager;
        $this->giftCardOrderEmailManager = $giftCardOrderEmailManager;
    }

    public function associateToCustomer(OrderInterface $order): void
    {
        $items = self::getOrderItemsThatAreGiftCards($order);

        if (count($items) === 0) {
            return;
        }

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        Assert::isInstanceOf($customer, CustomerInterface::class);

        foreach ($items as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCard = $unit->getGiftCard();
                Assert::notNull($giftCard);

                $giftCard->setCustomer($customer);
            }
        }

        $this->giftCardManager->flush();
    }

    public function enable(OrderInterface $order): void
    {
        $giftCards = $this->getGiftCards($order);

        if (count($giftCards) === 0) {
            return;
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->enable();
        }

        $this->giftCardManager->flush();
    }

    /**
     * Calls when Order this GiftCardCode was bought at
     * become cancelled
     */
    public function disable(OrderInterface $order): void
    {
        $giftCards = $this->getGiftCards($order);

        if (count($giftCards) === 0) {
            return;
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->disable();
        }

        $this->giftCardManager->flush();
    }

    public function send(OrderInterface $order): void
    {
        $giftCards = $this->getGiftCards($order);

        if (count($giftCards) === 0) {
            return;
        }

        $this->giftCardOrderEmailManager->sendEmailWithGiftCardsFromOrder($order, $giftCards);
    }

    /**
     * Returns all the gift cards that were bought on the given order
     *
     * @return GiftCardInterface[]
     */
    private function getGiftCards(OrderInterface $order): array
    {
        $giftCards = [];

        $items = self::getOrderItemsThatAreGiftCards($order);
        foreach ($items as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCard = $this->giftCardRepository->findOneByOrderItemUnit($unit);
                if (null === $giftCard) {
                    continue;
                }

                $giftCards[] = $giftCard;
            }
        }

        return $giftCards;
    }

    /**
     * @return Collection|OrderItemInterface[]
     */
    private static function getOrderItemsThatAreGiftCards(OrderInterface $order): Collection
    {
        return $order->getItems()->filter(static function (OrderItemInterface $item): bool {
            /** @var ProductInterface|null $product */
            $product = $item->getProduct();

            Assert::isInstanceOf($product, ProductInterface::class);

            return $product->isGiftCard();
        });
    }
}
