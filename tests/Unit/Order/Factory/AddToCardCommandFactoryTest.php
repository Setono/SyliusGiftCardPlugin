<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\Factory\AddToCartCommandFactory;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactory;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;

final class AddToCardCommandFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_new_with_cart_and_item(): void
    {
        $cart = new Order();
        $cartItem = new OrderItem();

        $giftCardInformationFactory = new GiftCardInformationFactory(GiftCardInformation::class);
        $factory = new AddToCartCommandFactory(AddToCartCommand::class, $giftCardInformationFactory);

        $addToCartCommand = $factory->createWithCartAndCartItem($cart, $cartItem);
        $this->assertEquals($cart, $addToCartCommand->getCart());
        $this->assertEquals($cartItem, $addToCartCommand->getCartItem());
        $this->assertInstanceOf(GiftCardInformation::class, $addToCartCommand->getGiftCardInformation());
    }
}
