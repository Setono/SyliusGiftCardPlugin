<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Sylius\Bundle\ApiBundle\Context\UserContextInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CreateServiceAliasesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // if this service is already defined, we don't need to do anything. It means the Sylius version is < 1.11
        if ($container->has('sylius.api.context.user')) {
            return;
        }

        // this should not be possible after the check above, but we need to check it, obviously. This service was added in 1.11
        if (!$container->has(UserContextInterface::class)) {
            return;
        }

        $container->setAlias('sylius.api.context.user', UserContextInterface::class);
    }
}
