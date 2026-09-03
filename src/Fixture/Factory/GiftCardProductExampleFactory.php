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
        Assert::string($name);
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
            ->setDefault('name', 'Gift card')
            ->setAllowedTypes('name', 'string')
            ->setDefault('code', function (Options $options): string {
                $name = $options['name'];
                Assert::string($name);

                return StringInflector::nameToCode($name);
            })
            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
            ->setDefault('price', GiftCardProductFactoryInterface::DEFAULT_PRICE)
            ->setAllowedTypes('price', 'int')
            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
            ->setDefault('delivery_types', GiftCardDeliveryType::cases())
            ->setAllowedTypes('delivery_types', 'array')
        ;
    }
}
