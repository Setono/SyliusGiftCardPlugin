<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;

final class ProductTraitTest extends TestCase
{
    #[Test]
    public function it_has_properties(): void
    {
        $product = new Product();

        $product->setGiftCard(true);
        $this->assertTrue($product->isGiftCard());

        $product->setGiftCardAmountConfigurable(true);
        $this->assertTrue($product->isGiftCardAmountConfigurable());
    }
}
