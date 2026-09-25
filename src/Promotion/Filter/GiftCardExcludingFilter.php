<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Promotion\Filter;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;

/**
 * Keeps the gift cards being bought out of the items a unit discount applies to. A gift card is worth the amount the
 * customer chose, so a promotion taking something off its line would sell the card below its value, and a coupon for
 * the whole shop would then buy full value gift cards at a discount.
 *
 * Decorates Sylius' product filter, which every unit discount action runs whatever the promotion filters on
 */
final class GiftCardExcludingFilter implements FilterInterface
{
    public function __construct(private readonly FilterInterface $decorated)
    {
    }

    /**
     * @param OrderItemInterface[] $items
     * @param array<array-key, mixed> $configuration
     *
     * @return OrderItemInterface[]
     */
    public function filter(array $items, array $configuration): array
    {
        return array_filter(
            $this->decorated->filter($items, $configuration),
            static fn (OrderItemInterface $item): bool => !self::isGiftCard($item),
        );
    }

    private static function isGiftCard(OrderItemInterface $item): bool
    {
        $product = $item->getProduct();

        return $product instanceof ProductInterface && $product->isGiftCard();
    }
}
