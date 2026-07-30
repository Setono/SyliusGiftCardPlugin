<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusGiftCardExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{
         *     pdf_rendering: array{
         *         default_orientation: string,
         *         available_orientations: list<string>,
         *         default_page_size: string,
         *         available_page_sizes: list<string>,
         *         preferred_page_sizes: list<string>,
         *     },
         *     code_length: int,
         *     driver: string,
         *     resources: array<string, mixed>
         * } $config
         *
         * @psalm-suppress PossiblyNullArgument
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $container->setParameter('setono_sylius_gift_card.code_length', $config['code_length']);
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.default_orientation',
            $config['pdf_rendering']['default_orientation'],
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.available_orientations',
            $config['pdf_rendering']['available_orientations'],
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.default_page_size',
            $config['pdf_rendering']['default_page_size'],
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.available_page_sizes',
            $config['pdf_rendering']['available_page_sizes'],
        );
        $container->setParameter(
            'setono_sylius_gift_card.pdf_rendering.preferred_page_sizes',
            $config['pdf_rendering']['preferred_page_sizes'],
        );

        $this->registerResources('setono_sylius_gift_card', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    #[\Override]
    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    #[\Override]
    protected function getMigrationsDirectory(): string
    {
        return '@SetonoSyliusGiftCardPlugin/src/Migrations';
    }

    #[\Override]
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
