<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/**
 * Turns fixture options into a call to the real factory: building the product is domain logic and lives in
 * {@see \Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactory}, so the admin panel creates gift card products the same way fixtures do
 */
class GiftCardProductExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    protected OptionsResolver $optionsResolver;

    /**
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        protected GiftCardProductFactoryInterface $giftCardProductFactory,
        protected RepositoryInterface $channelRepository,
    ) {
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function create(array $options = []): ProductInterface
    {
        $options = $this->optionsResolver->resolve($options);

        $code = $options['code'];
        Assert::string($code);
        $name = $options['name'];
        Assert::nullOrString($name);
        $price = $options['price'];
        Assert::integer($price);

        /** @var list<ChannelInterface> $channels */
        $channels = array_values((array) $options['channels']);

        /** @var list<GiftCardDeliveryType> $deliveryTypes */
        $deliveryTypes = array_values((array) $options['delivery_types']);

        return $this->giftCardProductFactory->create(
            $code,
            $name,
            $price,
            (bool) $options['enabled'],
            $channels,
            $deliveryTypes,
        );
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            // Left out, the factory names the product "Gift card" in the language of each locale
            ->setDefault('name', null)
            ->setAllowedTypes('name', ['null', 'string'])
            ->setDefault('code', function (Options $options): string {
                $name = $options['name'];
                Assert::nullOrString($name);

                return null === $name ? 'gift_card' : StringInflector::nameToCode($name);
            })
            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
            ->setDefault('price', GiftCardProductFactoryInterface::DEFAULT_PRICE)
            ->setAllowedTypes('price', 'int')
            // Sylius maps the channel price as Doctrine's integer type, a signed 32-bit integer in its portable type
            // system, which the database holds the product to
            ->setNormalizer('price', static function (Options $options, int $price): int {
                Assert::range($price, -2147483648, 2147483647, 'A gift card product price has to fit Sylius\' integer price column, from %2$s to %3$s minor units, got: %s');

                return $price;
            })
            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
            ->setDefault('delivery_types', GiftCardDeliveryType::cases())
            ->setAllowedTypes('delivery_types', 'array')
            // A fixture file gives the values, an application building on this factory may give the cases, and the
            // product factory takes cases. A type given twice still gets one variant, since a variant code is unique
            ->setNormalizer('delivery_types', static function (Options $options, array $deliveryTypes): array {
                $cases = [];
                foreach ($deliveryTypes as $deliveryType) {
                    if (!$deliveryType instanceof GiftCardDeliveryType) {
                        Assert::string($deliveryType);
                        $deliveryType = GiftCardDeliveryType::from($deliveryType);
                    }

                    $cases[$deliveryType->value] = $deliveryType;
                }

                return array_values($cases);
            })
        ;
    }
}
