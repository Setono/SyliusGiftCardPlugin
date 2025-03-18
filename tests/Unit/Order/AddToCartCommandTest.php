<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;

final class AddToCartCommandTest extends TestCase
{
    #[Test]
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
