<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class GiftCardProductExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    private const DELIVERY_OPTION_CODE = 'gift_card_delivery';

    protected OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<ProductInterface> $productFactory
     * @param FactoryInterface<ProductVariantInterface> $productVariantFactory
     * @param FactoryInterface<ProductOptionInterface> $productOptionFactory
     * @param FactoryInterface<ProductOptionValueInterface> $productOptionValueFactory
     * @param FactoryInterface<ChannelPricingInterface> $channelPricingFactory
     * @param RepositoryInterface<ProductOptionInterface> $productOptionRepository
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        protected FactoryInterface $productFactory,
        protected FactoryInterface $productVariantFactory,
        protected FactoryInterface $productOptionFactory,
        protected FactoryInterface $productOptionValueFactory,
        protected FactoryInterface $channelPricingFactory,
        protected RepositoryInterface $productOptionRepository,
        protected RepositoryInterface $channelRepository,
        protected RepositoryInterface $localeRepository,
        protected SlugGeneratorInterface $slugGenerator,
    ) {
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(array $options = []): ProductInterface
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var ProductInterface $product */
        $product = $this->productFactory->createNew();

        $code = $options['code'];
        Assert::string($code);
        $name = $options['name'];
        Assert::string($name);

        $product->setCode($code);
        $product->setEnabled((bool) $options['enabled']);
        $product->setGiftCard(true);
        $product->setVariantSelectionMethod(ProductInterface::VARIANT_SELECTION_CHOICE);

        foreach ($this->getLocales() as $localeCode) {
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);
            $product->setName($name);
            $product->setSlug($this->slugGenerator->generate($code));
        }

        /** @var list<ChannelInterface> $channels */
        $channels = $options['channels'];
        if ([] === $channels) {
            /** @var list<ChannelInterface> $channels */
            $channels = $this->channelRepository->findAll();
        }
        foreach ($channels as $channel) {
            $product->addChannel($channel);
        }

        $option = $this->provideDeliveryOption();
        $product->addOption($option);

        /** @var array<string, GiftCardDeliveryType> $deliveryTypes */
        $deliveryTypes = $options['delivery_types'];
        foreach ($deliveryTypes as $valueCode => $deliveryType) {
            $optionValue = $this->findOptionValue($option, $valueCode);
            Assert::notNull($optionValue);

            $variant = $this->createVariant($product, $code, $optionValue, $deliveryType, $channels, (int) $options['price']);
            $product->addVariant($variant);
        }

        return $product;
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    private function createVariant(
        ProductInterface $product,
        string $productCode,
        ProductOptionValueInterface $optionValue,
        GiftCardDeliveryType $deliveryType,
        array $channels,
        int $price,
    ): ProductVariantInterface {
        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantFactory->createNew();
        $variant->setCode(sprintf('%s-%s', $productCode, (string) $optionValue->getCode()));
        $variant->setProduct($product);
        $variant->addOptionValue($optionValue);
        $variant->setShippingRequired(GiftCardDeliveryType::Physical === $deliveryType);

        foreach ($this->getLocales() as $localeCode) {
            $variant->setCurrentLocale($localeCode);
            $variant->setFallbackLocale($localeCode);
            $variant->setName(ucfirst($deliveryType->value));
        }

        foreach ($channels as $channel) {
            /** @var ChannelPricingInterface $channelPricing */
            $channelPricing = $this->channelPricingFactory->createNew();
            $channelPricing->setChannelCode((string) $channel->getCode());
            $channelPricing->setPrice($price);
            $variant->addChannelPricing($channelPricing);
        }

        return $variant;
    }

    private function provideDeliveryOption(): ProductOptionInterface
    {
        /** @var ProductOptionInterface|null $option */
        $option = $this->productOptionRepository->findOneBy(['code' => self::DELIVERY_OPTION_CODE]);
        if (null !== $option) {
            return $option;
        }

        /** @var ProductOptionInterface $option */
        $option = $this->productOptionFactory->createNew();
        $option->setCode(self::DELIVERY_OPTION_CODE);

        foreach ($this->getLocales() as $localeCode) {
            $option->setCurrentLocale($localeCode);
            $option->setFallbackLocale($localeCode);
            $option->setName('Delivery');
        }

        foreach ([GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical] as $deliveryType) {
            /** @var ProductOptionValueInterface $value */
            $value = $this->productOptionValueFactory->createNew();
            $value->setCode($deliveryType->value);

            foreach ($this->getLocales() as $localeCode) {
                $value->setCurrentLocale($localeCode);
                $value->setFallbackLocale($localeCode);
                $value->setValue(ucfirst($deliveryType->value));
            }

            $option->addValue($value);
        }

        $this->productOptionRepository->add($option);

        return $option;
    }

    private function findOptionValue(ProductOptionInterface $option, string $valueCode): ?ProductOptionValueInterface
    {
        foreach ($option->getValues() as $value) {
            if ($value->getCode() === $valueCode) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getLocales(): array
    {
        /** @var list<LocaleInterface> $locales */
        $locales = $this->localeRepository->findAll();

        return array_values(array_filter(array_map(
            static fn (LocaleInterface $locale): ?string => $locale->getCode(),
            $locales,
        )));
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('name', 'Gift card')
            ->setAllowedTypes('name', 'string')
            ->setDefault('code', fn (Options $options): string => StringInflector::nameToCode((string) $options['name']))
            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
            ->setDefault('price', 5000)
            ->setAllowedTypes('price', 'int')
            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
            ->setDefault('delivery_types', [
                GiftCardDeliveryType::Virtual->value => GiftCardDeliveryType::Virtual,
                GiftCardDeliveryType::Physical->value => GiftCardDeliveryType::Physical,
            ])
            ->setAllowedTypes('delivery_types', 'array')
        ;
    }
}
