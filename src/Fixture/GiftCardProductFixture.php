<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GiftCardProductFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_gift_card_product';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->booleanNode('enabled')->end()
                ->integerNode('price')->end()
                ->arrayNode('channels')
                    ->scalarPrototype()->end()
                ->end()
        ;
    }
}
