<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Setono\SyliusGiftCardPlugin\Order\Factory\AddToCartCommandFactory;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface as BaseAddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class AddToCartCommandFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_wraps_a_plain_command_in_a_gift_card_aware_command(): void
    {
        $cart = $this->prophesize(OrderInterface::class)->reveal();
        $cartItem = $this->prophesize(OrderItemInterface::class)->reveal();
        $giftCardInformation = $this->prophesize(GiftCardInformationInterface::class)->reveal();

        // the inner factory produces a plain, non gift card aware command
        $innerCommand = $this->prophesize(BaseAddToCartCommandInterface::class);
        $innerCommand->getCart()->willReturn($cart);
        $innerCommand->getCartItem()->willReturn($cartItem);

        $decorated = $this->prophesize(AddToCartCommandFactoryInterface::class);
        $decorated->createWithCartAndCartItem($cart, $cartItem)->willReturn($innerCommand->reveal());

        $giftCardInformationFactory = $this->prophesize(GiftCardInformationFactoryInterface::class);
        $giftCardInformationFactory->createNew($cartItem)->willReturn($giftCardInformation);

        $factory = new AddToCartCommandFactory($decorated->reveal(), AddToCartCommand::class, $giftCardInformationFactory->reveal());

        $command = $factory->createWithCartAndCartItem($cart, $cartItem);

        self::assertInstanceOf(AddToCartCommandInterface::class, $command);
        self::assertSame($cart, $command->getCart());
        self::assertSame($cartItem, $command->getCartItem());
        self::assertSame($giftCardInformation, $command->getGiftCardInformation());
    }

    /** @test */
    public function it_returns_an_already_gift_card_aware_command_unchanged(): void
    {
        $cart = $this->prophesize(OrderInterface::class)->reveal();
        $cartItem = $this->prophesize(OrderItemInterface::class)->reveal();

        // the inner chain already produced a gift card aware command (e.g. an application decorator)
        $innerCommand = $this->prophesize(AddToCartCommandInterface::class)->reveal();

        $decorated = $this->prophesize(AddToCartCommandFactoryInterface::class);
        $decorated->createWithCartAndCartItem($cart, $cartItem)->willReturn($innerCommand);

        $giftCardInformationFactory = $this->prophesize(GiftCardInformationFactoryInterface::class);
        $giftCardInformationFactory->createNew(Argument::any())->shouldNotBeCalled();

        $factory = new AddToCartCommandFactory($decorated->reveal(), AddToCartCommand::class, $giftCardInformationFactory->reveal());

        self::assertSame($innerCommand, $factory->createWithCartAndCartItem($cart, $cartItem));
    }
}
