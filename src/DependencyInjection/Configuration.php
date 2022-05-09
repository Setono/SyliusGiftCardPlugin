<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardConfigurationRepository;
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
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
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

        /**
         * @psalm-suppress MixedMethodCall,PossiblyNullReference,PossiblyUndefinedMethod
         */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('pdf_rendering')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_orientation')
                            ->defaultValue(PdfRenderingOptionsProviderInterface::ORIENTATION_LANDSCAPE)
                        ->end()
                        ->arrayNode('available_orientations')
                            ->scalarPrototype()->end()
                            ->defaultValue(PdfRenderingOptionsProviderInterface::AVAILABLE_ORIENTATIONS)
                        ->end()
                        ->scalarNode('default_page_size')
                            ->defaultValue(PdfRenderingOptionsProviderInterface::PAGE_SIZE_A6)
                        ->end()
                        ->arrayNode('available_page_sizes')
                            ->scalarPrototype()->end()
                            ->defaultValue(PdfRenderingOptionsProviderInterface::AVAILABLE_PAGE_SIZES)
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
    }

    private function addGiftCardSection(NodeBuilder $nodeBuilder): void
    {
        /** @psalm-suppress MixedMethodCall,PossiblyNullReference,PossiblyUndefinedMethod */
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
        /** @psalm-suppress MixedMethodCall,PossiblyNullReference,PossiblyUndefinedMethod */
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
                            ->scalarNode('repository')->defaultValue(GiftCardConfigurationRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardConfigurationType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addGiftCardConfigurationImageSection(NodeBuilder $nodeBuilder): void
    {
        /** @psalm-suppress MixedMethodCall,PossiblyNullReference,PossiblyUndefinedMethod */
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
        /** @psalm-suppress MixedMethodCall,PossiblyNullReference,PossiblyUndefinedMethod */
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
