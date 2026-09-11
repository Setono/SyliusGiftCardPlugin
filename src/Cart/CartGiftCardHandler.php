<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Cart;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class CartGiftCardHandler implements CartGiftCardHandlerInterface
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardFactoryInterface $giftCardFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function handle(AddToCartCommandInterface $command): void
    {
        $cart = $command->getCart();
        $cartItem = $command->getCartItem();
        $information = $command->getGiftCardInformation();

        $amount = $information->getAmount();

        // Fix the price to the customer chosen amount and prevent Sylius from recalculating it
        $cartItem->setUnitPrice($amount);
        $cartItem->setImmutable(true);

        $channel = $cart->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $currencyCode = $cart->getCurrencyCode();
        Assert::notNull($currencyCode);

        foreach ($cartItem->getUnits() as $unit) {
            Assert::isInstanceOf($unit, OrderItemUnitInterface::class);

            if (null !== $unit->getGiftCard()) {
                continue;
            }

            $giftCard = $this->giftCardFactory->createForChannel($channel);
            $giftCard->setAmount($amount);
            $giftCard->setInitialAmount($amount);
            $giftCard->setCurrencyCode($currencyCode);
            $giftCard->setDeliveryType($this->resolveDeliveryType($unit));
            $giftCard->setDesign($information->getDesign());
            $giftCard->setCustomMessage($information->getCustomMessage());
            $giftCard->setOrderItemUnit($unit);
            $giftCard->disable();

            $this->getManager($giftCard)->persist($giftCard);
        }
    }

    private function resolveDeliveryType(OrderItemUnitInterface $unit): GiftCardDeliveryType
    {
        $orderItem = $unit->getOrderItem();
        $variant = $orderItem instanceof OrderItemInterface ? $orderItem->getVariant() : null;

        if ($variant instanceof ProductVariantInterface && $variant->isShippingRequired()) {
            return GiftCardDeliveryType::Physical;
        }

        return GiftCardDeliveryType::Virtual;
    }
}
