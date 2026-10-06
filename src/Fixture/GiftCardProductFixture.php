<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
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
                ->scalarNode('name')
                    ->info('The name of the product in every locale. Named "Gift card" in the language of each locale when left out')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('enabled')->end()
                ->integerNode('price')->end()
                ->arrayNode('channels')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('delivery_types')
                    ->info('The delivery types to create a variant for: virtual (emailed, not shipped) and/or physical (shipped). Both when left out')
                    ->requiresAtLeastOneElement()
                    ->enumPrototype()
                        ->values(array_map(static fn (GiftCardDeliveryType $deliveryType): string => $deliveryType->value, GiftCardDeliveryType::cases()))
                    ->end()
                ->end()
        ;
    }
}
