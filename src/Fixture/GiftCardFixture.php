<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GiftCardFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_gift_card';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')
                    ->info('The code of the card, of at least setono_sylius_gift_card.minimum_code_length characters. Generated when left out')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('channel')
                    ->info('The code of the channel. Picked at random when left out, among the channels whose base currency is the currency given, if one is')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('currency')
                    ->info('The code of the base currency of the channel, which is also the default. Orders are kept in the base currency, so a card in any other currency could never be redeemed and is refused')
                    ->cannotBeEmpty()
                ->end()
                ->floatNode('amount')->end()
                ->booleanNode('enabled')->end()
        ;
    }
}
