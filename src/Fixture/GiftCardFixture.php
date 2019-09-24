<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Tests\Fixtures\Builder\NodeBuilder;

final class GiftCardFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_sylius_gift_card';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        /** @var NodeBuilder $node */
        $node = $resourceNode->children();
        $node->scalarNode('product')->cannotBeEmpty();
        $node->arrayNode('channels')->scalarPrototype();
        $node->scalarNode('amount_product_option')->defaultValue(null);
        $node->scalarNode('amount')->defaultValue(null);
        $node->scalarNode('codes_count')->defaultValue(3)->cannotBeEmpty();
        $node->scalarNode('codes_used_count')->defaultValue(0)->cannotBeEmpty();
    }
}
