<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardDesignRepository;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepository;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardDesignType;
use Setono\SyliusGiftCardPlugin\Form\Type\GiftCardType;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Factory\TranslatableFactory;
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
                ->integerNode('code_length')
                    // A gift card code is a bearer token: anyone who knows it can spend the balance. The
                    // minimum keeps the search space out of reach of guessing (31^12 combinations), even
                    // though applying a code is rate limited as well
                    ->info('The number of significant characters in a generated gift card code (excluding group separators)')
                    ->defaultValue(16)
                    ->min(12)
                    ->max(255)
                ->end()
                ->scalarNode('default_validity_period')
                    ->info('A strtotime compatible interval (e.g. "3 years") added to the purchase date. Set to null to make gift cards valid forever')
                    ->defaultValue('3 years')
                    ->validate()
                        ->ifTrue(static fn ($value): bool => null !== $value && (!is_string($value) || false === strtotime(sprintf('+%s', $value))))
                        ->thenInvalid('The default_validity_period must be a valid strtotime interval, e.g. "3 years": %s')
                    ->end()
                ->end()
                ->arrayNode('purchase')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('minimum_amount')
                            ->info('The minimum purchasable gift card amount in minor units (e.g. cents)')
                            ->defaultValue(100)
                            ->min(1)
                        ->end()
                        ->integerNode('maximum_amount')
                            ->info('The maximum purchasable gift card amount in minor units. Set to null for no maximum')
                            ->defaultNull()
                            ->min(1)
                        ->end()
                        ->integerNode('maximum_message_length')
                            ->info('The maximum number of characters a customer may write on a gift card. The column is a TEXT, so the only hard ceiling is what fits in one')
                            ->defaultValue(200)
                            ->min(1)
                            ->max(65535)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('delivery')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('email_physical_cards')
                            ->info('Whether the email sent when an order is paid also carries the code and the PDF of a physical gift card, as a digital backup. Off by default: a physical card is shipped with its code printed on it, so emailing the code makes the card spendable before it arrives. Sending a card from the admin always includes them')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('redemption')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('payment_method_code')
                            ->info('The code of the payment method a redeemed gift card is paid with')
                            ->defaultValue('gift_card')
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('rate_limit')
                            ->info('Throttles how often a single visitor may try to apply a gift card code, so codes cannot be guessed by brute force')
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('limit')
                                    ->info('The number of attempts allowed within the interval')
                                    ->defaultValue(10)
                                    ->min(1)
                                ->end()
                                ->scalarNode('interval')
                                    ->info('The length of the sliding window: a number followed by second, minute, hour, day, week or month')
                                    ->defaultValue('1 minute')
                                    ->validate()
                                        ->ifTrue(static fn ($value): bool => !is_string($value) || 1 !== preg_match('/^\d+ (second|minute|hour|day|week|month)s?$/', $value))
                                        ->thenInvalid('The interval must be a number followed by second, minute, hour, day, week or month, e.g. "1 minute": %s')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pdf')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('page_size')
                            ->info('The paper size used when rendering gift card PDFs (any size supported by dompdf, e.g. A4, A6, letter). The card is scaled to fill it')
                            ->defaultValue('A6')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
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
        $this->addGiftCardDesignSection($resourcesNode);
        $this->addGiftCardTransactionSection($resourcesNode);
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
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(GiftCardRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardType::class)->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addGiftCardDesignSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card_design')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardDesign::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(GiftCardDesignRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue(GiftCardDesignType::class)->end()
                            ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->end()
                        ->end()
                    ->end()
                    ->arrayNode('translation')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->variableNode('options')->end()
                            ->arrayNode('classes')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('model')->defaultValue(GiftCardDesignTranslation::class)->cannotBeEmpty()->end()
                                    ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                                    ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('gift_card_design_image')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardDesignImage::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }

    private function addGiftCardTransactionSection(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('gift_card_transaction')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(GiftCardTransaction::class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue(EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }
}
