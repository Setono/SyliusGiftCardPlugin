<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class GiftCardDesignFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_gift_card_design';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->integerNode('position')->end()
                ->booleanNode('enabled')->end()
                ->scalarNode('front_image')->cannotBeEmpty()->end()
                ->scalarNode('back_image')->end()
                ->arrayNode('channels')
                    ->scalarPrototype()->end()
                ->end()
        ;
    }
}
