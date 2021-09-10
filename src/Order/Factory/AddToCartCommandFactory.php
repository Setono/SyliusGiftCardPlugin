<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Setono\SyliusGiftCardPlugin\Order\Dto\Factory\AddGiftCardToCartInformationFactoryInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class AddToCartCommandFactory implements AddToCartCommandFactoryInterface
{
    private string $className;

    private AddGiftCardToCartInformationFactoryInterface $extraInformationFactory;

    public function __construct(
        string $className,
        AddGiftCardToCartInformationFactoryInterface $extraInformationFactory
    ) {
        $this->className = $className;
        $this->extraInformationFactory = $extraInformationFactory;
    }

    public function createWithCartAndCartItem(OrderInterface $cart, OrderItemInterface $cartItem): AddToCartCommandInterface
    {
        /**
         * @var AddToCartCommandInterface $addToCartCommand
         * @psalm-suppress InvalidStringClass
         */
        $addToCartCommand = new $this->className($cart, $cartItem, $this->extraInformationFactory->createNew($cartItem));

        return $addToCartCommand;
    }
}
