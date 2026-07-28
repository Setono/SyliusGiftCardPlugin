<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\Product;

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
