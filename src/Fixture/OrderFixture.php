<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @todo Extract to external plugin
 */
class OrderFixture extends AbstractResourceFixture
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'setono_sylius_gift_card_payed_order';
    }

    /**
     * {@inheritdoc}
     */
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        /** @var NodeBuilder $node */
        $node = $resourceNode->children();
        $node->scalarNode('customer');
        $node->scalarNode('channel');
        $node->scalarNode('locale');
        $node->scalarNode('currency');

        /** @var NodeBuilder $itemsNode */
        $itemNode = $node->arrayNode('items')->arrayPrototype()->children();
        $itemNode->scalarNode('product');
        $itemNode->scalarNode('variant');
        $itemNode->scalarNode('quantity');

        $addressNode = $node->arrayNode('address')->children();
        $addressNode->scalarNode('first_name');
        $addressNode->scalarNode('last_name');
        $addressNode->scalarNode('street');
        $addressNode->scalarNode('country');
        $addressNode->scalarNode('city');
        $addressNode->scalarNode('postcode');

        $node->scalarNode('shipping_method');
        $node->scalarNode('payment_method');
        $node->scalarNode('notes');

        $node->scalarNode('checkout_completed_at');
    }

}
