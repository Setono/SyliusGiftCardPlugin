<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardExpiryResolverInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardOperator implements OrderGiftCardOperatorInterface
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardFactoryInterface $giftCardFactory,
        ManagerRegistry $managerRegistry,
        private readonly GiftCardEmailManagerInterface $emailManager,
        private readonly GiftCardBalanceOperatorInterface $balanceOperator,
        private readonly GiftCardExpiryResolverInterface $giftCardExpiryResolver,
    ) {
        $this->managerRegistry = $managerRegistry;
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

        // A bought card's validity counts from the purchase, which is now. The card add to cart created was given an
        // expiry when it went into the cart, possibly weeks ago, so every card on the order gets this one instead: the
        // time spent in the cart does not count, and the cards bought on one order expire at the same moment
        $expiresAt = $this->giftCardExpiryResolver->resolve();

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

                    $this->getManager($giftCard)->persist($giftCard);
                }

                // The card is worth the amount the customer chose, which is the price of its line. Not the unit total:
                // promotions never discount a gift card line, and tax charged on top of it, where a gift card product
                // carries a tax category, is not part of what the card is worth
                $amount = $item->getUnitPrice();
                $giftCard->setInitialAmount($amount);
                $giftCard->setAmount($amount);

                $giftCard->setExpiresAt($expiresAt);

                if (null !== $customer) {
                    $giftCard->setCustomer($customer);
                }
            }
        }
    }

    public function enable(OrderInterface $order): void
    {
        $giftCards = self::getGiftCards($order);
        if (0 === count($giftCards)) {
            return;
        }

        foreach ($giftCards as $giftCard) {
            $giftCard->enable();

            // Issuance is recorded here rather than when the card is created, because a pending card's amount
            // is re-snapshotted during reconciliation; this is the first moment the balance is final. The ledger
            // row names the order that paid for the card, so its ledger leads back to the money behind it. Nobody
            // is recorded as having issued it, not even an administrator who marked the payment completed: the
            // order issued the card, the administrator only said it was paid
            $this->balanceOperator->issue($giftCard, $order);
        }
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
