<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    public function __construct(
        private readonly GiftCardFactoryInterface $giftCardFactory,
        private readonly ObjectManager $giftCardManager,
        private readonly GiftCardEmailManagerInterface $emailManager,
    ) {
    }

    public function reconcile(OrderInterface $order): void
    {
        $items = self::getGiftCardItems($order);
        if (0 === count($items)) {
            return;
        }

        $channel = $order->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $currencyCode = $order->getCurrencyCode();
        Assert::notNull($currencyCode);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();

        foreach ($items as $item) {
            $template = self::findTemplateGiftCard($item);
            $deliveryType = self::resolveDeliveryType($item);

            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCard = $unit->getGiftCard();

                if (null === $giftCard) {
                    $giftCard = $this->giftCardFactory->createForChannel($channel);
                    $giftCard->setCurrencyCode($currencyCode);
                    $giftCard->setDeliveryType($deliveryType);
                    $giftCard->setDesign($template?->getDesign());
                    $giftCard->setCustomMessage($template?->getCustomMessage());
                    $giftCard->setOrderItemUnit($unit);
                    $giftCard->disable();

                    $this->giftCardManager->persist($giftCard);
                }

                // Snapshot the final paid amount (after any promotions) as the initial and current balance
                $total = $unit->getTotal();
                $giftCard->setInitialAmount($total);
                $giftCard->setAmount($total);

                if (null !== $customer) {
                    $giftCard->setCustomer($customer);
                }
            }
        }

        $this->giftCardManager->flush();
    }

    public function enable(OrderInterface $order): void
    {
        $giftCards = self::getGiftCards($order);
        if (0 === count($giftCards)) {
            return;
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->enable();
        }

        $this->giftCardManager->flush();
    }

    public function send(OrderInterface $order): void
    {
        $giftCards = self::getGiftCards($order);
        if (0 === count($giftCards)) {
            return;
        }

        $this->emailManager->sendGiftCardsFromOrder($order, $giftCards);
    }

    public function disable(OrderInterface $order): void
    {
        $giftCards = self::getGiftCards($order);
        if (0 === count($giftCards)) {
            return;
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->disable();
        }

        $this->giftCardManager->flush();
    }

    /**
     * @return list<GiftCardInterface>
     */
    private static function getGiftCards(OrderInterface $order): array
    {
        $giftCards = [];

        foreach (self::getGiftCardItems($order) as $item) {
            /** @var OrderItemUnitInterface $unit */
            foreach ($item->getUnits() as $unit) {
                $giftCard = $unit->getGiftCard();
                if (null !== $giftCard) {
                    $giftCards[] = $giftCard;
                }
            }
        }

        return $giftCards;
    }

    /**
     * @return list<OrderItemInterface>
     */
    private static function getGiftCardItems(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product instanceof ProductInterface && $product->isGiftCard()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function findTemplateGiftCard(OrderItemInterface $item): ?GiftCardInterface
    {
        /** @var OrderItemUnitInterface $unit */
        foreach ($item->getUnits() as $unit) {
            $giftCard = $unit->getGiftCard();
            if (null !== $giftCard) {
                return $giftCard;
            }
        }

        return null;
    }

    private static function resolveDeliveryType(OrderItemInterface $item): GiftCardDeliveryType
    {
        $variant = $item->getVariant();

        if ($variant instanceof ProductVariantInterface && $variant->isShippingRequired()) {
            return GiftCardDeliveryType::Physical;
        }

        return GiftCardDeliveryType::Virtual;
    }
}
