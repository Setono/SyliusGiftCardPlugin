<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    /** @var GiftCardRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var EntityManagerInterface */
    private $giftCardManager;

    /** @var GiftCardOrderEmailManagerInterface */
    private $giftCardOrderEmailManager;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        GiftCardRepositoryInterface $giftCardCodeRepository,
        EntityManagerInterface $giftCardManager,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->giftCardManager = $giftCardManager;
        $this->giftCardOrderEmailManager = $giftCardOrderEmailManager;
    }

    public function create(OrderInterface $order): void
    {
        $items = self::getOrderItemsThatAreGiftCards($order);

        foreach ($items as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCardCode = $this->giftCardFactory->createFromOrderItemUnit($unit);

                $this->giftCardManager->persist($giftCardCode);
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
            $giftCard->enable();
        }

        $this->giftCardManager->flush();
    }

    public function send(OrderInterface $order): void
    {
        $giftCards = $this->getGiftCards($order);

        if (count($giftCards) === 0) {
            return;
        }

        $codes = array_map(static function (GiftCardInterface $giftCard) {
            return $giftCard->getCode();
        }, $giftCards);

        $this->giftCardOrderEmailManager->sendEmailWithGiftCardCodes($order, $codes);
    }

    /**
     * @return GiftCardInterface[]
     */
    private function getGiftCards(OrderInterface $order): array
    {
        $giftCards = [];

        $items = self::getOrderItemsThatAreGiftCards($order);
        foreach ($items as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCard = $this->giftCardCodeRepository->findOneByOrderItemUnit($unit);
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
        return $order->getItems()->filter(static function (OrderItemInterface $item) {
            /** @var ProductInterface|null $product */
            $product = $item->getProduct();

            Assert::isInstanceOf($product, ProductInterface::class);

            return $product->isGiftCard();
        });
    }
}
