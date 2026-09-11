<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Factory;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

/**
 * Decorates the core add to cart command factory so the created command carries gift card information
 */
final class AddToCartCommandFactory implements AddToCartCommandFactoryInterface
{
    /**
     * @param class-string<AddToCartCommandInterface> $className
     */
    public function __construct(
        private readonly AddToCartCommandFactoryInterface $decorated,
        private readonly string $className,
        private readonly GiftCardInformationFactoryInterface $giftCardInformationFactory,
    ) {
    }

    public function createWithCartAndCartItem(OrderInterface $cart, OrderItemInterface $cartItem): AddToCartCommandInterface
    {
        $command = $this->decorated->createWithCartAndCartItem($cart, $cartItem);

        // A lower priority decorator already produced a gift card aware command; respect it instead of clobbering it.
        // This lets an application that extends our command (and points the command class parameter at it) compose
        // cleanly with this decorator
        if ($command instanceof AddToCartCommandInterface) {
            return $command;
        }

        return new $this->className(
            $command->getCart(),
            $command->getCartItem(),
            $this->giftCardInformationFactory->createNew($cartItem),
        );
    }
}
