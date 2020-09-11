<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use function array_key_exists;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveCatalogPromotionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (array_key_exists('SetonoSyliusCatalogPromotionPlugin', $bundles)) {
            return;
        }

        // Remove services declared for SyliusCatalogPromotionPlugin
        $container->removeDefinition('setono_sylius_gift_card.catalog_promotion.rule.is_not_gift_card');
    }
}
