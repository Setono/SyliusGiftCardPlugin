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
        /**
         * @var array{
         *     pdf_rendering: array{
         *         default_orientation: string,
         *         available_orientations: array<array-key, string>,
         *         default_page_size: string,
         *         available_page_sizes: array<array-key, string>,
         *     },
         *     code_length: int,
         *     driver: string,
         *     resources: array<string, mixed>
         * } $config
         * @psalm-suppress PossiblyNullArgument
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_gift_card.code_length', $config['code_length']);
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.default_orientation',
            $config['pdf_rendering']['default_orientation']
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.available_orientations',
            $config['pdf_rendering']['available_orientations']
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.default_page_size',
            $config['pdf_rendering']['default_page_size']
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.available_page_sizes',
            $config['pdf_rendering']['available_page_sizes']
        );

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
