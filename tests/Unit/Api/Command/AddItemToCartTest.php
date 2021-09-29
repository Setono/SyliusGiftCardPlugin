<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Command;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AddItemToCart;

class AddItemToCartTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $command = new AddItemToCart('variant', 1, 15, 'Custom message');

        $this->assertInstanceOf(AddItemToCart::class, $command);
    }
    /**
     * @test
     */
    public function it_has_its_parents_properties(): void
    {
        $command = new AddItemToCart('variant', 1);

        $this->assertEquals('variant', $command->productVariantCode);
        $this->assertEquals(1, $command->quantity);
    }

    /**
     * @test
     */
    public function it_has_nullable_amount(): void
    {
        $command = new AddItemToCart('variant', 1);
        $this->assertNull($command->getAmount());
        $command = new AddItemToCart('variant', 1, 150);
        $this->assertEquals(150, $command->getAmount());
    }

    /**
     * @test
     */
    public function it_has_nullable_custom_message(): void
    {
        $command = new AddItemToCart('variant', 1);
        $this->assertNull($command->getCustomMessage());
        $command = new AddItemToCart('variant', 1, null, 'Custom message');
        $this->assertEquals('Custom message', $command->getCustomMessage());
    }
}
