<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariant;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Product;

final class OrderItemTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_asserts_two_gift_cards_can_not_be_identical(): void
    {
        $firstOrderItem = new OrderItem();
        $secondOrderItem = new OrderItem();

        $variant = new ProductVariant();
        $product = new Product();
        $variant->setProduct($product);
        $firstOrderItem->setVariant($variant);

        $variant->setProduct($product);
        $secondOrderItem->setVariant($variant);

        $this->assertTrue($firstOrderItem->equals($secondOrderItem));

        $product->setGiftCard(true);
        $this->assertFalse($firstOrderItem->equals($secondOrderItem));
    }
}
