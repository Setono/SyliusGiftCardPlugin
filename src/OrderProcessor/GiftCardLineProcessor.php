<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Keeps the gift card lines of a cart whole, whenever the cart is processed.
 *
 * Every unit of a line carries a card held like the line's other cards (OrderGiftCardOperatorInterface::completeLines()),
 * so a unit added by raising the quantity on the cart page has one before Sylius can drop the unit add to cart gave it.
 *
 * A gift card line costs the amount the customer chose, and is immutable so Sylius' prices recalculator leaves it at
 * that rather than pricing it at the variant's price. A card held in another currency than the cart's (bought while
 * browsing in it, #488) is worth its amount in that currency, so its line costs that amount converted into the cart's
 * currency, at the exchange rate the shop keeps, and costs it again whenever the cart is processed: the way every other
 * price the customer sees in that currency follows the rate. A card in the cart's currency is priced at its amount when
 * it is added, and is left alone here
 */
final class GiftCardLineProcessor implements OrderProcessorInterface
{
    public function __construct(
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly OrderGiftCardOperatorInterface $giftCardOperator,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface || !$order->canBeProcessed()) {
            return;
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null === $orderCurrencyCode) {
            return;
        }

        $this->giftCardOperator->completeLines($order);

        foreach ($order->getItems() as $item) {
            // Through the variant, as Sylius' order item reads its product off a variant it assumes it has
            $product = $item->getVariant()?->getProduct();
            if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
                continue;
            }

            // The cards of a line all come from the same add to cart, so they are held in the same currency and amount
            $giftCard = self::findGiftCard($item);
            $currencyCode = $giftCard?->getCurrencyCode();
            if (null === $giftCard || null === $currencyCode || $currencyCode === $orderCurrencyCode) {
                continue;
            }

            $item->setUnitPrice($this->currencyConverter->convert($giftCard->getInitialAmount(), $currencyCode, $orderCurrencyCode));
        }
    }

    /**
     * The card of any of the line's units, as a unit Sylius has just added may not have one yet
     */
    private static function findGiftCard(OrderItemInterface $item): ?GiftCardInterface
    {
        foreach ($item->getUnits() as $unit) {
            $giftCard = $unit instanceof OrderItemUnitInterface ? $unit->getGiftCard() : null;
            if (null !== $giftCard) {
                return $giftCard;
            }
        }

        return null;
    }
}
