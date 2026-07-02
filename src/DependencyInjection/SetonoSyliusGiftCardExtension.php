<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Yaml\Yaml;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{
         *     code_length: int,
         *     default_validity_period: string|null,
         *     purchase: array{minimum_amount: int, maximum_amount: int|null},
         *     redemption: array{mode: string, payment_method_code: string},
         *     pdf: array{page_size: string},
         *     resources: array<string, mixed>,
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_gift_card.code_length', $config['code_length']);
        $container->setParameter('setono_sylius_gift_card.default_validity_period', $config['default_validity_period']);
        $container->setParameter('setono_sylius_gift_card.purchase.minimum_amount', $config['purchase']['minimum_amount']);
        $container->setParameter('setono_sylius_gift_card.purchase.maximum_amount', $config['purchase']['maximum_amount']);
        $container->setParameter('setono_sylius_gift_card.redemption.mode', $config['redemption']['mode']);
        $container->setParameter('setono_sylius_gift_card.redemption.payment_method_code', $config['redemption']['payment_method_code']);
        $container->setParameter('setono_sylius_gift_card.pdf.page_size', $config['pdf']['page_size']);
        $container->setParameter(
            'setono_sylius_gift_card.default_design_image_path',
            dirname(__DIR__) . '/Resources/fixtures/default_background.png',
        );

        $this->registerResources(
            'setono_sylius_gift_card',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );

        $loader->load('services.xml');
        $loader->load(sprintf('services/redemption/%s.xml', $config['redemption']['mode']));
    }

    /**
     * Prepends configuration for other bundles so the host application
     * does not have to import any configuration files manually
     */
    public function prepend(ContainerBuilder $container): void
    {
        $files = glob(__DIR__ . '/../Resources/config/prepend/*.yaml');
        if (false === $files) {
            return;
        }

        foreach ($files as $file) {
            /** @var mixed $parsed */
            $parsed = Yaml::parseFile($file);
            if (!is_array($parsed)) {
                continue;
            }

            /** @var mixed $config */
            foreach ($parsed as $extension => $config) {
                if (!is_string($extension) || !is_array($config)) {
                    continue;
                }

                if (!$container->hasExtension($extension)) {
                    continue;
                }

                $container->prependExtensionConfig($extension, $config);
            }
        }
    }
}
