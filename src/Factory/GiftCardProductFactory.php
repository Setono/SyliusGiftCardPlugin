<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardProductFactory implements GiftCardProductFactoryInterface
{
    use ORMTrait;

    private const DELIVERY_OPTION_CODE = 'gift_card_delivery';

    /**
     * The delivery option this factory created and persisted, until whoever persists the product flushes it. A
     * repository lookup only sees what is flushed, so a second product created before that flush (the product fixture
     * creates several in one unit of work) would otherwise get a second option with the same, unique, code
     */
    private ?ProductOptionInterface $createdDeliveryOption = null;

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
        private readonly FactoryInterface $productFactory,
        private readonly FactoryInterface $productVariantFactory,
        private readonly FactoryInterface $productOptionFactory,
        private readonly FactoryInterface $productOptionValueFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly RepositoryInterface $productOptionRepository,
        private readonly RepositoryInterface $channelRepository,
        private readonly RepositoryInterface $localeRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function create(
        string $code,
        string $name,
        int $price = self::DEFAULT_PRICE,
        bool $enabled = true,
        array $channels = [],
        array $deliveryTypes = [],
    ): ProductInterface {
        if ([] === $channels) {
            /** @var list<ChannelInterface> $channels */
            $channels = array_values($this->channelRepository->findAll());
        }

        if ([] === $deliveryTypes) {
            $deliveryTypes = GiftCardDeliveryType::cases();
        }

        /** @var ProductInterface $product */
        $product = $this->productFactory->createNew();
        $product->setCode($code);
        $product->setEnabled($enabled);
        $product->setGiftCard(true);
        $product->setVariantSelectionMethod(ProductInterface::VARIANT_SELECTION_CHOICE);

        foreach ($this->getLocales() as $localeCode) {
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);
            $product->setName($name);
            $product->setSlug($this->getSlug($code));
        }

        foreach ($channels as $channel) {
            $product->addChannel($channel);
        }

        $option = $this->provideDeliveryOption();
        $product->addOption($option);

        foreach ($deliveryTypes as $deliveryType) {
            $optionValue = $this->findOptionValue($option, $deliveryType);
            Assert::notNull($optionValue, sprintf(
                'The product option "%s" has no value "%s" (or "%s")',
                self::DELIVERY_OPTION_CODE,
                $this->getOptionValueCode($deliveryType),
                $deliveryType->value,
            ));

            $product->addVariant($this->createVariant($product, $code, $optionValue, $deliveryType, $channels, $price));
        }

        return $product;
    }

    public function getSlug(string $code): string
    {
        return $this->slugGenerator->generate($code);
    }

    public function getVariantCodes(string $code, array $deliveryTypes = []): array
    {
        return array_map(
            fn (GiftCardDeliveryType $deliveryType): string => $this->getVariantCode($code, $deliveryType),
            [] === $deliveryTypes ? GiftCardDeliveryType::cases() : $deliveryTypes,
        );
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
        $variant->setCode($this->getVariantCode($productCode, $deliveryType));
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

    /**
     * A variant is named after its product and its delivery type
     */
    private function getVariantCode(string $productCode, GiftCardDeliveryType $deliveryType): string
    {
        return sprintf('%s_%s', $productCode, $deliveryType->value);
    }

    /**
     * The delivery option is shared by every gift card product, so it is created once and reused afterwards. A new one
     * is persisted, not flushed: the caller's flush writes it with the product
     */
    private function provideDeliveryOption(): ProductOptionInterface
    {
        /** @var ProductOptionInterface|null $option */
        $option = $this->productOptionRepository->findOneBy(['code' => self::DELIVERY_OPTION_CODE]);
        if (null !== $option) {
            return $option;
        }

        $created = $this->createdDeliveryOption;
        if (null !== $created && $this->getManager($created)->contains($created)) {
            return $created;
        }

        /** @var ProductOptionInterface $option */
        $option = $this->productOptionFactory->createNew();
        $option->setCode(self::DELIVERY_OPTION_CODE);

        foreach ($this->getLocales() as $localeCode) {
            $option->setCurrentLocale($localeCode);
            $option->setFallbackLocale($localeCode);
            $option->setName('Delivery');
        }

        foreach (GiftCardDeliveryType::cases() as $deliveryType) {
            /** @var ProductOptionValueInterface $value */
            $value = $this->productOptionValueFactory->createNew();
            $value->setCode($this->getOptionValueCode($deliveryType));

            foreach ($this->getLocales() as $localeCode) {
                $value->setCurrentLocale($localeCode);
                $value->setFallbackLocale($localeCode);
                $value->setValue(ucfirst($deliveryType->value));
            }

            $option->addValue($value);
        }

        $this->getManager($option)->persist($option);

        return $this->createdDeliveryOption = $option;
    }

    /**
     * The delivery option created before its values got codes of their own has values coded with the bare delivery
     * type. Such a value is still used for its delivery type, unless the option has one with the prefixed code too
     */
    private function findOptionValue(ProductOptionInterface $option, GiftCardDeliveryType $deliveryType): ?ProductOptionValueInterface
    {
        $bareValue = null;

        foreach ($option->getValues() as $value) {
            if ($value->getCode() === $this->getOptionValueCode($deliveryType)) {
                return $value;
            }

            if ($value->getCode() === $deliveryType->value) {
                $bareValue = $value;
            }
        }

        return $bareValue;
    }

    /**
     * Option value codes are unique across all options, and a shop's own option may well have a value "physical", so
     * the delivery option's values are prefixed with the option's code
     */
    private function getOptionValueCode(GiftCardDeliveryType $deliveryType): string
    {
        return sprintf('%s_%s', self::DELIVERY_OPTION_CODE, $deliveryType->value);
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
        ), static fn (?string $code): bool => null !== $code));
    }
}
