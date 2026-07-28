<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\Product;
use Sylius\Component\Core\Model\ProductVariant;

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
