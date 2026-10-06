<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\Builder\NullableIntegerNodeDefinition;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Definition\Builder\StrtotimeIntervalNodeDefinition;
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
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * A gift card code is a bearer token: anyone who knows it can spend the balance. No code a card is issued with
     * from now on, generated or typed, may be shorter than this, which keeps the search space out of reach of guessing
     * (31^12 combinations for a generated code), even though applying a code is rate limited as well
     */
    public const MINIMUM_CODE_LENGTH = 12;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_gift_card');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            // A generated code has to meet the minimum like any other, or the shop would issue cards with codes it
            // refuses an admin to type
            ->validate()
                ->ifTrue(static fn (array $config): bool => isset($config['code_length'], $config['minimum_code_length']) && $config['code_length'] < $config['minimum_code_length'])
                ->then(self::refuseCodeLengthBelowMinimum(...))
            ->end()
            ->children()
                ->integerNode('code_length')
                    ->info('The number of significant characters in a generated gift card code (excluding group separators). At least minimum_code_length')
                    ->defaultValue(16)
                    ->min(self::MINIMUM_CODE_LENGTH)
                    ->max(255)
                ->end()
                ->integerNode('minimum_code_length')
                    ->info(sprintf('The fewest significant characters a gift card code may have when the card is issued: a code typed in the admin, a code given to the fixtures, and code_length. At least %d; cards that already exist keep their code, whatever its length', self::MINIMUM_CODE_LENGTH))
                    ->defaultValue(self::MINIMUM_CODE_LENGTH)
                    ->min(self::MINIMUM_CODE_LENGTH)
                    ->max(255)
                ->end()
                // A validate() rule would be run on the dummy value Symfony checks an environment variable with, and
                // refuse every interval taken from one. This node leaves such an interval to GiftCardExpiryResolver,
                // which checks it where it uses it
                ->append(
                    (new StrtotimeIntervalNodeDefinition('default_validity_period'))
                        ->info('A strtotime compatible interval (e.g. "3 years") added to the purchase date. Set to null to make gift cards valid forever')
                        ->defaultValue('3 years'),
                )
                ->arrayNode('purchase')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('minimum_amount')
                            ->info('The minimum purchasable gift card amount in minor units (e.g. cents)')
                            ->defaultValue(100)
                            ->min(1)
                        ->end()
                        // An integer node refuses an explicit null, which is what this option says to write
                        ->append(
                            (new NullableIntegerNodeDefinition('maximum_amount'))
                                ->info('The maximum purchasable gift card amount in minor units. Set to null for no maximum')
                                ->defaultNull()
                                ->min(1),
                        )
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
                        ->append(self::rateLimiterNode(
                            'rate_limiter',
                            'The rate limiter factory, as a service id, that throttles how often a single visitor (session) may try to apply a gift card code, so codes cannot be guessed by brute force. Defaults to the limiter the plugin registers under framework.rate_limiter (10 attempts per minute); point it at a limiter of your own, or set it to null to stop throttling per session',
                            SetonoSyliusGiftCardExtension::RATE_LIMITER_NAME,
                        ))
                        ->append(self::rateLimiterNode(
                            'ip_rate_limiter',
                            'The rate limiter factory, as a service id, that throttles attempts to apply a gift card code per client IP, which stops a guesser that discards its session cookie. Everyone behind one address shares this budget, so it defaults to a larger one than rate_limiter: the limiter the plugin registers under framework.rate_limiter (50 attempts per minute). Behind a reverse proxy, framework.trusted_proxies must be configured, or every customer shares the proxy\'s bucket. Point it at a limiter of your own, or set it to null to stop throttling per IP',
                            SetonoSyliusGiftCardExtension::IP_RATE_LIMITER_NAME,
                        ))
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

    /**
     * @param array{code_length: int, minimum_code_length: int} $config
     */
    private static function refuseCodeLengthBelowMinimum(array $config): never
    {
        throw new \InvalidArgumentException(sprintf(
            'The code_length (%d) must be at least the minimum_code_length (%d)',
            $config['code_length'],
            $config['minimum_code_length'],
        ));
    }

    /**
     * A rate limiter option: the service id of a rate limiter factory, defaulting to one the plugin prepends onto
     * framework.rate_limiter, or null to leave that bucket out
     */
    private static function rateLimiterNode(string $name, string $info, string $defaultLimiter): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition($name);
        $node
            ->info($info)
            ->defaultValue('limiter.' . $defaultLimiter)
            ->validate()
                ->ifTrue(static fn ($value): bool => null !== $value && (!is_string($value) || '' === $value))
                ->thenInvalid(sprintf('The %s option must be the id of a rate limiter factory service, or null to turn that throttling off: %%s', $name))
            ->end()
        ;

        return $node;
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
