<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\OrderItemTrait;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class OrderItemTraitTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * A gift card item must equal itself, otherwise Sylius' OrderItemController::resolveAddedOrderItem()
     * (`getItems()->filter(equals)->first()`) returns false and add-to-cart fails with a TypeError.
     */
    public function a_gift_card_item_equals_itself(): void
    {
        $item = $this->giftCardOrderItem();

        self::assertTrue($item->equals($item));
    }

    /** @test */
    public function two_distinct_gift_card_items_are_never_equal(): void
    {
        $variant = $this->giftCardVariant();

        $a = new GiftCardTestOrderItem();
        $a->setVariant($variant);
        $b = new GiftCardTestOrderItem();
        $b->setVariant($variant);

        self::assertFalse($a->equals($b));
        self::assertFalse($b->equals($a));
    }

    private function giftCardOrderItem(): GiftCardTestOrderItem
    {
        $item = new GiftCardTestOrderItem();
        $item->setVariant($this->giftCardVariant());

        return $item;
    }

    private function giftCardVariant(): ProductVariantInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isGiftCard()->willReturn(true);

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product->reveal());

        return $variant->reveal();
    }
}

final class GiftCardTestOrderItem extends BaseOrderItem
{
    use OrderItemTrait;
}
