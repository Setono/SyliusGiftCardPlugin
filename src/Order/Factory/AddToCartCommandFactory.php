<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class AddToCartCommandFactory implements AddToCartCommandFactoryInterface
{
    /** @var class-string<AddToCartCommandInterface> */
    private string $className;

    private GiftCardInformationFactoryInterface $giftCardInformationFactory;

    /**
     * @param class-string<AddToCartCommandInterface> $className
     */
    public function __construct(
        string $className,
        GiftCardInformationFactoryInterface $giftCardInformationFactory
    ) {
        $this->className = $className;
        $this->giftCardInformationFactory = $giftCardInformationFactory;
    }

    public function createWithCartAndCartItem(OrderInterface $cart, OrderItemInterface $cartItem): AddToCartCommandInterface
    {
        return new $this->className($cart, $cartItem, $this->giftCardInformationFactory->createNew($cartItem));
    }
}
