<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardChannelConfigurationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationImageType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_gift_card');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('pdf_rendering')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_orientation')->defaultValue('Portrait')->end()
                        ->arrayNode('available_orientations')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                'Portrait',
                                'Landscape',
                            ])
                        ->end()
                        ->scalarNode('default_page_size')->defaultValue('A4')->end()
                        ->arrayNode('available_page_sizes')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                'A0',
                                'A1',
                                'A2',
                                'A3',
                                'A4',
                                'A5',
                                'A6',
                                'A7',
                                'A8',
                                'A9',
                                'B0',
                                'B1',
                                'B2',
                                'B3',
                                'B4',
                                'B5',
                                'B6',
                                'B7',
                                'B8',
                                'B9',
                                'B10',
                                'C5E',
                                'Comm10E',
                                'DLE',
                                'Executive',
                                'Folio',
                                'Ledger',
                                'Legal',
                                'Letter',
                                'Tabloid',
                            ])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->integerNode('code_length')
                    ->defaultValue(20)
                    ->info('The length of the generated gift card code')
                    ->min(1)
                    ->max(255)
                    ->example(16)
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $resourcesNode = $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
        ;

        $this->addGiftCardSection($resourcesNode);
        $this->addGiftCardConfigurationSection($resourcesNode);
        $this->addGiftCardConfigurationImageSection($resourcesNode);
        $this->addChannelConfigurationSection($resourcesNode);

        $resourcesNode
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addGiftCardSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCard::class)->cannotBeEmpty()->end()
                            ->scalarNode('interface')->defaultValue(GiftCardInterface::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(GiftCardRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addGiftCardConfigurationSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card_configuration')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardConfiguration::class)->cannotBeEmpty()->end()
                            ->scalarNode('interface')->defaultValue(GiftCardConfigurationInterface::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardConfigurationType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addGiftCardConfigurationImageSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card_configuration_image')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardConfigurationImage::class)->cannotBeEmpty()->end()
                            ->scalarNode('interface')->defaultValue(GiftCardConfigurationImageInterface::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardConfigurationImageType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addChannelConfigurationSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card_channel_configuration')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardChannelConfiguration::class)->cannotBeEmpty()->end()
                            ->scalarNode('interface')->defaultValue(GiftCardChannelConfigurationInterface::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardChannelConfigurationType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }
}
