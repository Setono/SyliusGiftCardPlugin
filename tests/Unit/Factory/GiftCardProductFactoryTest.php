<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGenerator;
use Sylius\Component\Product\Model\ProductOption;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValue;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * A gift card product is an ordinary Sylius product flagged as a gift card, with a "delivery" option whose values
 * decide whether a card is shipped. The factory assembles one in every locale of the shop, so the merchant only has to
 * review it
 */
final class GiftCardProductFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RepositoryInterface<ProductOptionInterface>> */
    private ObjectProphecy $productOptionRepository;

    /** @var ObjectProphecy<RepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channelRepository;

    /** @var ObjectProphecy<FactoryInterface<ProductOptionInterface>> */
    private ObjectProphecy $productOptionFactory;

    private ChannelInterface $web;

    private ChannelInterface $mobile;

    protected function setUp(): void
    {
        $this->web = $this->channel('WEB');
        $this->mobile = $this->channel('MOBILE');

        /** @var ObjectProphecy<RepositoryInterface<ProductOptionInterface>> $productOptionRepository */
        $productOptionRepository = $this->prophesize(RepositoryInterface::class);
        $this->productOptionRepository = $productOptionRepository;
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn(null);
        $this->productOptionRepository->add(Argument::any())->will(static function (): void {});

        /** @var ObjectProphecy<RepositoryInterface<ChannelInterface>> $channelRepository */
        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $this->channelRepository = $channelRepository;
        $this->channelRepository->findAll()->willReturn([$this->web, $this->mobile]);

        /** @var ObjectProphecy<FactoryInterface<ProductOptionInterface>> $productOptionFactory */
        $productOptionFactory = $this->prophesize(FactoryInterface::class);
        $this->productOptionFactory = $productOptionFactory;
        $this->productOptionFactory->createNew()->will(static fn (): ProductOption => new ProductOption());
    }

    /** @test */
    public function it_creates_a_gift_card_product_named_in_every_locale_of_the_shop(): void
    {
        $product = $this->factory()->create('gift_card', 'Gift card');

        self::assertSame('gift_card', $product->getCode());
        self::assertTrue($product->isGiftCard());
        self::assertTrue($product->isEnabled());
        // The customer picks physical or virtual from the option, not from a list of variant names
        self::assertSame(ProductInterface::VARIANT_SELECTION_CHOICE, $product->getVariantSelectionMethod());

        foreach (['en_US', 'da_DK'] as $localeCode) {
            self::assertSame('Gift card', $product->getTranslation($localeCode)->getName());
            self::assertSame('gift-card', $product->getTranslation($localeCode)->getSlug());
        }
    }

    /**
     * Whoever picks the code checks the slug it will get against the slugs other products use, so the slug the
     * factory reports for a code must be the one it gives the product
     *
     * @test
     */
    public function it_gives_the_product_the_slug_it_reports_for_the_code(): void
    {
        $factory = $this->factory();

        self::assertSame('gift-card-2', $factory->getSlug('gift_card_2'));

        $product = $factory->create('gift_card_2', 'Gift card');
        foreach (['en_US', 'da_DK'] as $localeCode) {
            self::assertSame($factory->getSlug('gift_card_2'), $product->getTranslation($localeCode)->getSlug());
        }
    }

    /** @test */
    public function it_sells_the_product_in_every_channel_unless_told_otherwise(): void
    {
        $product = $this->factory()->create('gift_card', 'Gift card');

        self::assertSame([$this->web, $this->mobile], array_values($product->getChannels()->toArray()));

        foreach ($this->variants($product) as $variant) {
            self::assertSame(5000, $variant->getChannelPricingForChannel($this->web)?->getPrice());
            self::assertSame(5000, $variant->getChannelPricingForChannel($this->mobile)?->getPrice());
        }
    }

    /**
     * Whether a card is shipped is what makes it physical: the delivery type of a bought card is derived from the
     * variant's shipping requirement
     *
     * @test
     */
    public function it_creates_a_virtual_and_a_physical_variant_that_only_the_physical_one_ships(): void
    {
        $variants = $this->variants($this->factory()->create('gift_card', 'Gift card'));

        self::assertSame(['gift_card_virtual', 'gift_card_physical'], array_keys($variants));
        self::assertFalse($variants['gift_card_virtual']->isShippingRequired());
        self::assertTrue($variants['gift_card_physical']->isShippingRequired());

        foreach (['virtual' => 'Virtual', 'physical' => 'Physical'] as $value => $name) {
            $variant = $variants['gift_card_' . $value];
            self::assertSame([$value], array_values(array_map(
                static fn ($optionValue): ?string => $optionValue->getCode(),
                $variant->getOptionValues()->toArray(),
            )));
            self::assertSame($name, $variant->getTranslation('en_US')->getName());
            self::assertSame($name, $variant->getTranslation('da_DK')->getName());
        }
    }

    /** @test */
    public function it_creates_the_delivery_option_when_the_shop_has_none_yet(): void
    {
        $this->productOptionRepository->add(Argument::type(ProductOptionInterface::class))->shouldBeCalledOnce();

        $product = $this->factory()->create('gift_card', 'Gift card');

        $options = $product->getOptions()->toArray();
        self::assertCount(1, $options);

        $option = reset($options);
        self::assertInstanceOf(ProductOptionInterface::class, $option);
        self::assertSame('gift_card_delivery', $option->getCode());
        self::assertSame('Delivery', $option->getTranslation('da_DK')->getName());

        $values = [];
        foreach ($option->getValues() as $value) {
            $values[(string) $value->getCode()] = $value->getTranslation('en_US')->getValue();
        }
        self::assertSame(['virtual' => 'Virtual', 'physical' => 'Physical'], $values);
    }

    /**
     * Every gift card product shares the option, so a second product reuses it instead of adding another option
     * with the same code
     *
     * @test
     */
    public function it_reuses_the_delivery_option_the_shop_already_has(): void
    {
        $existing = $this->deliveryOption('virtual', 'physical');
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn($existing);
        $this->productOptionRepository->add(Argument::any())->shouldNotBeCalled();
        $this->productOptionFactory->createNew()->shouldNotBeCalled();

        $product = $this->factory()->create('gift_card_2', 'Gift card');

        self::assertSame([$existing], array_values($product->getOptions()->toArray()));

        $variants = $this->variants($product);
        self::assertSame(['gift_card_2_virtual', 'gift_card_2_physical'], array_keys($variants));
        self::assertSame($existing->getValues()->first(), $variants['gift_card_2_virtual']->getOptionValues()->first());
    }

    /** @test */
    public function it_only_creates_what_it_is_asked_for(): void
    {
        $this->channelRepository->findAll()->shouldNotBeCalled();

        $product = $this->factory()->create(
            'digital_gift_card',
            'Digital gift card',
            price: 2500,
            enabled: false,
            channels: [$this->mobile],
            deliveryTypes: [GiftCardDeliveryType::Virtual],
        );

        self::assertFalse($product->isEnabled());
        self::assertSame([$this->mobile], array_values($product->getChannels()->toArray()));

        $variants = $this->variants($product);
        self::assertSame(['digital_gift_card_virtual'], array_keys($variants));
        self::assertSame(2500, $variants['digital_gift_card_virtual']->getChannelPricingForChannel($this->mobile)?->getPrice());
        self::assertNull($variants['digital_gift_card_virtual']->getChannelPricingForChannel($this->web));
    }

    /**
     * A shop whose delivery option was edited so that it lacks one of the values cannot get a working product, and
     * should be told why rather than get a product without the variant
     *
     * @test
     */
    public function it_refuses_when_the_delivery_option_lacks_a_delivery_type(): void
    {
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn($this->deliveryOption('virtual'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The product option "gift_card_delivery" has no value "physical"');

        $this->factory()->create('gift_card', 'Gift card');
    }

    private function factory(): GiftCardProductFactory
    {
        $productFactory = $this->prophesize(FactoryInterface::class);
        $productFactory->createNew()->will(static fn (): Product => new Product());

        $productVariantFactory = $this->prophesize(FactoryInterface::class);
        $productVariantFactory->createNew()->will(static fn (): ProductVariant => new ProductVariant());

        $productOptionValueFactory = $this->prophesize(FactoryInterface::class);
        $productOptionValueFactory->createNew()->will(static fn (): ProductOptionValue => new ProductOptionValue());

        $channelPricingFactory = $this->prophesize(FactoryInterface::class);
        $channelPricingFactory->createNew()->will(static fn (): ChannelPricing => new ChannelPricing());

        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $localeRepository->findAll()->willReturn([$this->locale('en_US'), $this->locale('da_DK')]);

        return new GiftCardProductFactory(
            $productFactory->reveal(),
            $productVariantFactory->reveal(),
            $this->productOptionFactory->reveal(),
            $productOptionValueFactory->reveal(),
            $channelPricingFactory->reveal(),
            $this->productOptionRepository->reveal(),
            $this->channelRepository->reveal(),
            $localeRepository->reveal(),
            new SlugGenerator(),
        );
    }

    /**
     * @return array<string, ProductVariantInterface> the variants by code, in the order they were created
     */
    private function variants(ProductInterface $product): array
    {
        $variants = [];
        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            $variants[(string) $variant->getCode()] = $variant;
        }

        return $variants;
    }

    private function deliveryOption(string ...$valueCodes): ProductOptionInterface
    {
        $option = new ProductOption();
        $option->setCode('gift_card_delivery');

        foreach ($valueCodes as $valueCode) {
            $value = new ProductOptionValue();
            $value->setCode($valueCode);
            $option->addValue($value);
        }

        return $option;
    }

    private function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }

    private function locale(string $code): LocaleInterface
    {
        $locale = new Locale();
        $locale->setCode($code);

        return $locale;
    }
}
