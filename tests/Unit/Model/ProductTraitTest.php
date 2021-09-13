<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariant;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Product;

final class ProductTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_properties(): void
    {
        $product = new Product();

        $product->setGiftCard(true);
        $this->assertTrue($product->isGiftCard());

        $product->setGiftCardAmountConfigurable(true);
        $this->assertTrue($product->isGiftCardAmountConfigurable());
    }
}
