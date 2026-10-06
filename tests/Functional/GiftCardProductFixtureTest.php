<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Fixture\Factory\GiftCardProductExampleFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The setono_gift_card_product fixture hands its options to the same factory the admin's "create gift card product"
 * button uses, so this checks that the options reach it and that the product lands in the database complete
 */
final class GiftCardProductFixtureTest extends GiftCardFunctionalTestCase
{
    use LoadsFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel();
        $this->createChannel('OTHER_CHANNEL');
    }

    /** @test */
    public function it_loads_a_gift_card_product_with_the_given_options(): void
    {
        $this->loadFixture('setono_gift_card_product', ['custom' => [[
            'code' => 'holiday_card',
            'name' => 'Holiday card',
            'price' => 2500,
            'enabled' => false,
            'channels' => ['TEST_CHANNEL'],
        ]]]);

        $product = $this->findProduct('holiday_card');
        self::assertTrue($product->isGiftCard());
        self::assertFalse($product->isEnabled());
        self::assertSame('Holiday card', $product->getTranslation('en_US')->getName());
        self::assertSame(['TEST_CHANNEL'], $this->channelCodesOf($product));

        // one variant per delivery type, told apart by whether it has to be shipped
        self::assertSame(
            ['holiday_card_physical' => true, 'holiday_card_virtual' => false],
            $this->shippingRequiredByVariantCode($product),
        );

        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            self::assertSame(['TEST_CHANNEL' => 2500], $this->pricesOf($variant));
        }
    }

    /**
     * A shop may sell only one kind of gift card, so a product can be seeded with the variants for the delivery types
     * named and no others. Without delivery types it gets both, as the test above shows.
     *
     * A product with one delivery type leaves the customer nothing to choose, so it gets no delivery option and is a
     * simple product, which the shop shows without a variant choice. With both, the option is what the customer
     * chooses by
     *
     * @test
     */
    public function it_loads_gift_card_products_with_only_the_given_delivery_types(): void
    {
        $this->loadFixture('setono_gift_card_product', ['custom' => [
            ['code' => 'virtual_card', 'name' => 'Virtual card', 'delivery_types' => ['virtual']],
            ['code' => 'physical_card', 'name' => 'Physical card', 'delivery_types' => ['physical']],
            ['code' => 'both_card', 'name' => 'Both card', 'delivery_types' => ['physical', 'virtual']],
        ]]);

        $virtual = $this->findProduct('virtual_card');
        self::assertTrue($virtual->isGiftCard());
        self::assertSame(['virtual_card_virtual' => false], $this->shippingRequiredByVariantCode($virtual));
        self::assertTrue($virtual->isSimple());
        self::assertSame([], $this->optionCodesOf($virtual));

        $physical = $this->findProduct('physical_card');
        self::assertSame(['physical_card_physical' => true], $this->shippingRequiredByVariantCode($physical));
        self::assertTrue($physical->isSimple());
        self::assertSame([], $this->optionCodesOf($physical));

        $both = $this->findProduct('both_card');
        self::assertSame(['both_card_physical' => true, 'both_card_virtual' => false], $this->shippingRequiredByVariantCode($both));
        self::assertFalse($both->isSimple());
        self::assertSame(['gift_card_delivery'], $this->optionCodesOf($both));
    }

    /**
     * A variant code is unique, so a delivery type named twice gets one variant rather than failing the flush. It is
     * still one delivery type, so the product is as simple as one that names it once
     *
     * @test
     */
    public function it_creates_one_variant_for_a_delivery_type_given_twice(): void
    {
        $this->loadFixture('setono_gift_card_product', ['custom' => [
            ['code' => 'twice_card', 'name' => 'Twice card', 'delivery_types' => ['virtual', 'virtual']],
        ]]);

        $product = $this->findProduct('twice_card');
        self::assertSame(['twice_card_virtual' => false], $this->shippingRequiredByVariantCode($product));
        self::assertTrue($product->isSimple());
        self::assertSame([], $this->optionCodesOf($product));
    }

    /**
     * Random products are built from the prototype, which reaches the example factory without passing the fixture's
     * tree, so the example factory turns the values into the enum itself
     *
     * @test
     */
    public function it_seeds_a_random_product_with_the_delivery_types_of_the_prototype(): void
    {
        $this->loadFixture('setono_gift_card_product', ['random' => 1, 'prototype' => ['delivery_types' => ['virtual']]]);

        self::assertSame(['gift_card_virtual' => false], $this->shippingRequiredByVariantCode($this->findProduct('gift_card')));
    }

    /**
     * Applications building on the example factory may hand it the delivery types as the enum rather than their values
     *
     * @test
     */
    public function its_example_factory_takes_delivery_types_as_values_or_as_the_enum(): void
    {
        /** @var GiftCardProductExampleFactory $factory */
        $factory = self::getContainer()->get(GiftCardProductExampleFactory::class);

        self::assertSame(
            ['by_value_physical' => true],
            $this->shippingRequiredByVariantCode($factory->create(['code' => 'by_value', 'delivery_types' => ['physical']])),
        );
        self::assertSame(
            ['by_case_virtual' => false],
            $this->shippingRequiredByVariantCode($factory->create(['code' => 'by_case', 'delivery_types' => [GiftCardDeliveryType::Virtual]])),
        );
    }

    /**
     * The fixture creates every product before it flushes, and they all share the one delivery option, whose code is
     * unique. The factory does not flush, so it hands the second product the option it created for the first instead of
     * looking for one in the database, where it is not yet
     *
     * @test
     */
    public function it_loads_several_gift_card_products_sharing_one_delivery_option(): void
    {
        $this->loadFixture('setono_gift_card_product', ['custom' => [
            ['code' => 'first_card', 'name' => 'First card'],
            ['code' => 'second_card', 'name' => 'Second card'],
        ]]);

        $first = $this->findProduct('first_card');
        $second = $this->findProduct('second_card');

        $options = array_values($first->getOptions()->toArray());
        self::assertCount(1, $options);
        self::assertSame(
            array_map(static fn (ProductOptionInterface $option): mixed => $option->getId(), $options),
            array_map(static fn (ProductOptionInterface $option): mixed => $option->getId(), array_values($second->getOptions()->toArray())),
        );

        /** @var RepositoryInterface<ProductOptionInterface> $optionRepository */
        $optionRepository = self::getContainer()->get('sylius.repository.product_option');
        self::assertCount(1, $optionRepository->findBy(['code' => 'gift_card_delivery']));
    }

    /**
     * Only a name is needed: the code is derived from it, and the product is enabled, priced at the default and sold
     * in every channel
     *
     * @test
     */
    public function it_defaults_to_an_enabled_product_in_every_channel(): void
    {
        $this->loadFixture('setono_gift_card_product', ['custom' => [['name' => 'Holiday gift card']]]);

        $product = $this->findProduct('holiday_gift_card');
        self::assertTrue($product->isGiftCard());
        self::assertTrue($product->isEnabled());
        self::assertEqualsCanonicalizing(['TEST_CHANNEL', 'OTHER_CHANNEL'], $this->channelCodesOf($product));
        self::assertCount(2, $product->getVariants());

        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            self::assertSame(['OTHER_CHANNEL' => 5000, 'TEST_CHANNEL' => 5000], $this->pricesOf($variant));
        }
    }

    /**
     * Without a name the product is named "Gift card" in the language of each locale, the way the admin's button
     * names it
     *
     * @test
     */
    public function it_names_the_product_gift_card_in_the_language_of_each_locale_when_nothing_is_given(): void
    {
        $danish = new Locale();
        $danish->setCode('da_DK');
        $this->manager->persist($danish);
        $this->manager->flush();

        $this->loadFixture('setono_gift_card_product', ['random' => 1]);

        $product = $this->findProduct('gift_card');
        self::assertSame('Gift card', $product->getTranslation('en_US')->getName());
        self::assertSame('Gavekort', $product->getTranslation('da_DK')->getName());
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidProductOptions
     *
     * @param array<string, mixed> $options
     */
    public function it_rejects_invalid_options(array $options): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadFixture('setono_gift_card_product', ['custom' => [$options]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidProductOptions(): iterable
    {
        yield 'an empty code' => [['code' => '']];
        yield 'a price that is not an integer' => [['price' => 25.5]];
        yield 'a price that is not a number' => [['price' => 'free']];
        yield 'a delivery type that is neither virtual nor physical' => [['delivery_types' => ['virtual', 'digital']]];
        yield 'a delivery type spelled as the enum case rather than its value' => [['delivery_types' => ['Virtual']]];
        yield 'a delivery type that is not in a list' => [['delivery_types' => 'virtual']];
        // a product without a variant cannot be sold, so an empty list is a mistake rather than a way to say "both"
        yield 'no delivery types' => [['delivery_types' => []]];
        yield 'an unknown option' => [['colour' => 'gold']];
    }

    private function findProduct(string $code): ProductInterface
    {
        $this->manager->clear();

        /** @var ProductRepositoryInterface<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');

        $product = $repository->findOneByCode($code);
        self::assertInstanceOf(ProductInterface::class, $product, sprintf('the fixture should have created product %s', $code));

        return $product;
    }

    /**
     * @return list<string>
     */
    private function channelCodesOf(ProductInterface $product): array
    {
        return array_values(array_map(
            static fn (ChannelInterface $channel): string => (string) $channel->getCode(),
            $product->getChannels()->toArray(),
        ));
    }

    /**
     * @return list<string>
     */
    private function optionCodesOf(ProductInterface $product): array
    {
        return array_values(array_map(
            static fn (ProductOptionInterface $option): string => (string) $option->getCode(),
            $product->getOptions()->toArray(),
        ));
    }

    /**
     * @return array<string, bool>
     */
    private function shippingRequiredByVariantCode(ProductInterface $product): array
    {
        $shippingRequired = [];
        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            $shippingRequired[(string) $variant->getCode()] = $variant->isShippingRequired();
        }
        ksort($shippingRequired);

        return $shippingRequired;
    }

    /**
     * @return array<string, int|null>
     */
    private function pricesOf(ProductVariantInterface $variant): array
    {
        $prices = [];
        foreach ($variant->getChannelPricings() as $channelPricing) {
            self::assertInstanceOf(ChannelPricingInterface::class, $channelPricing);
            $prices[(string) $channelPricing->getChannelCode()] = $channelPricing->getPrice();
        }
        ksort($prices);

        return $prices;
    }
}
