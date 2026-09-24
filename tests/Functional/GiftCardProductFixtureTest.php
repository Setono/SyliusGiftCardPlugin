<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
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

    /** @test */
    public function it_names_the_product_gift_card_when_nothing_is_given(): void
    {
        $this->loadFixture('setono_gift_card_product', ['random' => 1]);

        self::assertSame('Gift card', $this->findProduct('gift_card')->getTranslation('en_US')->getName());
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
