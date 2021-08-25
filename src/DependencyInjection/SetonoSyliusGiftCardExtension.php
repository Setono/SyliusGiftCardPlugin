<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use function array_key_exists;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        /** @var int $codeLength */
        $codeLength = $config['code_length'];
        $container->setParameter('setono_sylius_gift_card.code_length', $codeLength);

        $this->registerResources('setono_sylius_gift_card', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');

        if ($container->hasParameter('kernel.bundles')) {
            /** @var array $bundles */
            $bundles = $container->getParameter('kernel.bundles');
            if (array_key_exists('SetonoSyliusCatalogPromotionPlugin', $bundles)) {
                $loader->load('services/conditional/catalog_promotion_rule.xml');
            }
        }
    }
}
