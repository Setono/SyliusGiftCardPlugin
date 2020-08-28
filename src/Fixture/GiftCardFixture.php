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
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('channel')->cannotBeEmpty()->end()
                ->scalarNode('currency')->cannotBeEmpty()->end()
                ->floatNode('amount')->end()
                ->booleanNode('enabled')->end()
        ;
    }
}
