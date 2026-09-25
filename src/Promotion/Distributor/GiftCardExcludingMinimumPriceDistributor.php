<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Promotion\Distributor;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Distributor\MinimumPriceDistributorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Webmozart\Assert\Assert;

/**
 * Spreads an order discount over the order's items other than the gift cards being bought. A gift card is worth the
 * amount the customer chose, so its line never takes a share of a promotion. The amount spread is already taken from
 * the items without the gift cards (OrderTrait::getPromotionSubjectTotal()), so the other items do not end up with the
 * share the gift cards would have had.
 *
 * Decorates the distributor Sylius' order percentage and order fixed discount actions spread their amount with
 */
final class GiftCardExcludingMinimumPriceDistributor implements MinimumPriceDistributorInterface
{
    public function __construct(private readonly MinimumPriceDistributorInterface $decorated)
    {
    }

    /**
     * @param array<array-key, mixed> $orderItems
     *
     * @return array<array-key, mixed>
     */
    public function distribute(array $orderItems, int $amount, ChannelInterface $channel, bool $appliesOnDiscounted): array
    {
        Assert::allIsInstanceOf($orderItems, OrderItemInterface::class);

        $discountable = array_values(array_filter(
            $orderItems,
            static fn (OrderItemInterface $orderItem): bool => !self::isGiftCard($orderItem),
        ));

        if (count($discountable) === count($orderItems)) {
            return $this->decorated->distribute($orderItems, $amount, $channel, $appliesOnDiscounted);
        }

        $distributed = [] === $discountable ? [] : array_values(
            $this->decorated->distribute($discountable, $amount, $channel, $appliesOnDiscounted),
        );

        // The amounts are handed to the order's items by position, so every gift card keeps its place with nothing
        $amounts = [];
        $i = 0;
        foreach ($orderItems as $orderItem) {
            if (self::isGiftCard($orderItem)) {
                $amounts[] = 0;

                continue;
            }

            $share = $distributed[$i++] ?? 0;
            Assert::integer($share);
            $amounts[] = $share;
        }

        return $amounts;
    }

    private static function isGiftCard(OrderItemInterface $orderItem): bool
    {
        $product = $orderItem->getProduct();

        return $product instanceof ProductInterface && $product->isGiftCard();
    }
}
