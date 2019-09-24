<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Manager;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Webmozart\Assert\Assert;

final class GiftCardManager implements GiftCardManagerInterface
{
    /** @var GiftCardFactoryInterface */
    private $giftCardCodeFactory;

    /** @var GiftCardOrderEmailManagerInterface */
    private $giftCardOrderEmailManager;

    /** @var EntityManagerInterface */
    private $giftCardManager;

    public function __construct(
        GiftCardFactoryInterface $giftCardCodeFactory,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager,
        EntityManagerInterface $giftCardManager
    ) {
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardOrderEmailManager = $giftCardOrderEmailManager;
        $this->giftCardManager = $giftCardManager;
    }

    public function createFromOrder(OrderInterface $order): void
    {
        $giftCardCodes = [];

        if (null === $order->getChannel()) {
            return;
        }

        $items = self::getOrderItemsThatAreGiftCards($order);

        foreach ($items as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCardCode = $this->giftCardCodeFactory->createFromOrderItemUnit($unit);

                $giftCardCodes[] = $giftCardCode;

                $this->giftCardManager->persist($giftCardCode);
            }
        }

        $this->giftCardManager->flush();

        // todo we should not send codes here
        if (count($giftCardCodes) > 0) {
            $this->giftCardOrderEmailManager->sendEmailWithGiftCardCodes($order, $giftCardCodes);
        }
    }

    public function enableGiftCard(GiftCardInterface $giftCard): void
    {
        $giftCard->enable();

        $this->giftCardManager->flush();
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
