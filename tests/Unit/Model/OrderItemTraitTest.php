<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\OrderItemTrait;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;
use Sylius\Component\Core\Model\ProductInterface as BaseProductInterface;
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

    /**
     * The trait sits on every order item of the application, so adding another product of a variant already in the
     * cart still merges into that line the way Sylius does it
     *
     * @test
     */
    public function two_items_of_the_same_ordinary_variant_are_equal(): void
    {
        $variant = $this->variant($this->ordinaryProduct());

        $a = new GiftCardTestOrderItem();
        $a->setVariant($variant);
        $b = new GiftCardTestOrderItem();
        $b->setVariant($variant);

        self::assertTrue($a->equals($b));
    }

    /** @test */
    public function a_product_class_without_the_gift_card_flag_is_merged_the_way_sylius_does_it(): void
    {
        $variant = $this->variant($this->prophesize(BaseProductInterface::class)->reveal());

        $a = new GiftCardTestOrderItem();
        $a->setVariant($variant);
        $b = new GiftCardTestOrderItem();
        $b->setVariant($variant);

        self::assertTrue($a->equals($b));
    }

    /** @test */
    public function items_of_different_variants_are_never_equal(): void
    {
        $a = new GiftCardTestOrderItem();
        $a->setVariant($this->variant($this->ordinaryProduct()));
        $b = new GiftCardTestOrderItem();
        $b->setVariant($this->variant($this->ordinaryProduct()));

        self::assertFalse($a->equals($b));
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

        return $this->variant($product->reveal());
    }

    private function ordinaryProduct(): ProductInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->isGiftCard()->willReturn(false);

        return $product->reveal();
    }

    private function variant(BaseProductInterface $product): ProductVariantInterface
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product);

        return $variant->reveal();
    }
}

final class GiftCardTestOrderItem extends BaseOrderItem
{
    use OrderItemTrait;
}
