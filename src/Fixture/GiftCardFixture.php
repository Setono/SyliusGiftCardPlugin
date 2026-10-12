<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Webmozart\Assert\Assert;

class GiftCardFixture extends AbstractResourceFixture
{
    public function __construct(
        ObjectManager $objectManager,
        ExampleFactoryInterface $exampleFactory,
        protected readonly GiftCardCodeNormalizerInterface $giftCardCodeNormalizer,
    ) {
        parent::__construct($objectManager, $exampleFactory);
    }

    public function getName(): string
    {
        return 'setono_gift_card';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')
                    ->info('The code of the card, of at least setono_sylius_gift_card.minimum_code_length characters once it is normalized: stored in capitals with everything but the letters A to Z and the digits dropped, the way the cart looks codes up. Generated when left out')
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

        // The node of the list of entries, which AbstractResourceFixture builds and only hands this method the
        // prototype of
        $resourcesNode = $resourceNode->end();
        Assert::isInstanceOf($resourcesNode, ArrayNodeDefinition::class);

        // Two entries whose codes normalize to the same code would be the same card: the second would quietly update
        // the first, or, within the batch of ten entries flushed together, fail on the unique code with an error of
        // the database's that names neither. So the list is refused before anything is written
        $resourcesNode
            ->validate()
                ->always(function (array $entries): array {
                    $this->assertCodesAreUnique($entries);

                    return $entries;
                })
            ->end()
        ;
    }

    /**
     * @param array<array-key, mixed> $entries
     */
    private function assertCodesAreUnique(array $entries): void
    {
        /** @var array<string, array{array-key, string}> $seen the entry and the code as written, by the normalized code */
        $seen = [];

        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !isset($entry['code']) || !is_string($entry['code'])) {
                continue;
            }

            $code = $this->giftCardCodeNormalizer->normalize($entry['code']);
            if (!isset($seen[$code])) {
                $seen[$code] = [$key, $entry['code']];

                continue;
            }

            [$firstKey, $firstCode] = $seen[$code];

            // Symfony's configuration turns it into an InvalidConfigurationException naming the path of the list
            throw new \InvalidArgumentException(sprintf(
                'The entries "%s" and "%s" give the same gift card code once it is normalized ("%s" and "%s" are both %s). Each card needs a code of its own',
                $firstKey,
                $key,
                $firstCode,
                $entry['code'],
                $code,
            ));
        }
    }
}
