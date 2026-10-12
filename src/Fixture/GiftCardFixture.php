<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
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
                    ->info('The code of the card, of at least setono_sylius_gift_card.minimum_code_length letters and digits. Stored in capitals with dashes and spaces dropped, the way the cart looks codes up. Generated when left out')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('channel')
                    ->info('The code of the channel. Picked at random when left out, among the channels offering the currency given, if one is')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('currency')
                    ->info('The code of a currency the channel offers: its base currency, which is also the default, or one of its other currencies. A card in another currency than the base pays converted at the shop\'s exchange rate; a card in a currency the channel does not offer could never be redeemed and is refused')
                    ->cannotBeEmpty()
                ->end()
                ->floatNode('amount')->end()
                ->booleanNode('enabled')->end()
                ->enumNode('delivery_type')
                    ->info('Whether the card is virtual (delivered by email) or physical (shipped with the code printed on it). Virtual when left out')
                    ->values(array_map(static fn (GiftCardDeliveryType $deliveryType): string => $deliveryType->value, GiftCardDeliveryType::cases()))
                ->end()
        ;
    }
}
