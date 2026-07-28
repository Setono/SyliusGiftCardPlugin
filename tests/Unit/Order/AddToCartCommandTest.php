<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\Order;
use Setono\SyliusGiftCardPlugin\Tests\TestApplication\Entity\OrderItem;

final class AddToCartCommandTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_properties(): void
    {
        $cart = new Order();
        $cartItem = new OrderItem();
        $giftCardInformation = new GiftCardInformation(80, 'message');

        $addToCartCommand = new AddToCartCommand($cart, $cartItem, $giftCardInformation);
        $this->assertEquals($cart, $addToCartCommand->getCart());
        $this->assertEquals($cartItem, $addToCartCommand->getCartItem());
        $this->assertEquals($giftCardInformation, $addToCartCommand->getGiftCardInformation());
    }
}
