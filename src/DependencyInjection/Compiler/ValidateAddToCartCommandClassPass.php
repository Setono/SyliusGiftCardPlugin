<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The plugin decorates the core add to cart command factory so the created command carries gift card information.
 * If an application customizes the add to cart command it must stay compatible with that contract, so we fail fast
 * (with an actionable message) when the configured command class does not implement our interface, instead of letting
 * it surface later as an opaque "form view data is the wrong type" error
 */
final class ValidateAddToCartCommandClassPass implements CompilerPassInterface
{
    private const PARAMETER = 'setono_sylius_gift_card.order.model.add_to_cart_command.class';

    public function process(ContainerBuilder $container): void
    {
        $class = $container->getParameter(self::PARAMETER);

        if (is_string($class) && is_a($class, AddToCartCommandInterface::class, true)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'The add to cart command class configured in the "%s" parameter must implement "%s", but "%s" does not. ' .
            'This plugin decorates the add to cart command factory to attach gift card information; if your application ' .
            'already customizes the add to cart command, make your command class extend "%s" (or implement the interface) ' .
            'and point that parameter at it.',
            self::PARAMETER,
            AddToCartCommandInterface::class,
            is_string($class) ? $class : get_debug_type($class),
            AddToCartCommand::class,
        ));
    }
}
