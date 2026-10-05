<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

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

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var list<object> what the factory persisted, which the entity manager then contains */
    private array $persisted = [];

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
        $this->productOptionRepository->add(Argument::any())->shouldNotBeCalled();

        $persisted = &$this->persisted;
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->manager->persist(Argument::any())->will(static function (array $arguments) use (&$persisted): void {
            $persisted[] = $arguments[0];
        });
        $this->manager->contains(Argument::any())->will(static function (array $arguments) use (&$persisted): bool {
            return in_array($arguments[0], $persisted, true);
        });
        $this->manager->flush()->shouldNotBeCalled();

        /** @var ObjectProphecy<RepositoryInterface<ChannelInterface>> $channelRepository */
        $channelRepository = $this->prophesize(RepositoryInterface::class);
        $this->channelRepository = $channelRepository;
        $this->channelRepository->findAll()->willReturn([$this->web, $this->mobile]);

        /** @var ObjectProphecy<FactoryInterface<ProductOptionInterface>> $productOptionFactory */
        $productOptionFactory = $this->prophesize(FactoryInterface::class);
        $this->productOptionFactory = $productOptionFactory;
        $this->productOptionFactory->createNew()->will(static fn (): ProductOption => new ProductOption());
    }

    /**
     * The shop shows the product's name to its customers, so a product created without a name is named in the
     * language of each locale rather than in English for all of them
     *
     * @test
     */
    public function it_creates_a_gift_card_product_named_in_the_language_of_every_locale_of_the_shop(): void
    {
        $product = $this->factory()->create('gift_card');

        self::assertSame('gift_card', $product->getCode());
        self::assertTrue($product->isGiftCard());
        self::assertTrue($product->isEnabled());
        // The customer picks physical or virtual from the option, not from a list of variant names
        self::assertSame(ProductInterface::VARIANT_SELECTION_CHOICE, $product->getVariantSelectionMethod());

        self::assertSame('Gift card', $product->getTranslation('en_US')->getName());
        self::assertSame('Gavekort', $product->getTranslation('da_DK')->getName());

        foreach (['en_US', 'da_DK'] as $localeCode) {
            self::assertSame('gift-card', $product->getTranslation($localeCode)->getSlug());
        }
    }

    /** @test */
    public function it_gives_the_product_the_name_it_is_given_in_every_locale(): void
    {
        $product = $this->factory()->create('gift_card', 'Present');

        self::assertSame('Present', $product->getTranslation('en_US')->getName());
        self::assertSame('Present', $product->getTranslation('da_DK')->getName());
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

    /**
     * Whoever picks the code checks the variant codes it will get against the variant codes of other products, so the
     * codes the factory reports must be the ones it gives the variants, for every delivery type or only the ones asked
     * for
     *
     * @test
     */
    public function it_gives_the_variants_the_codes_it_reports_for_the_product_code(): void
    {
        $factory = $this->factory();

        self::assertSame(['gift_card_2_virtual', 'gift_card_2_physical'], $factory->getVariantCodes('gift_card_2'));
        self::assertSame(
            $factory->getVariantCodes('gift_card_2'),
            array_keys($this->variants($factory->create('gift_card_2', 'Gift card'))),
        );

        self::assertSame(['gift_card_2_physical'], $factory->getVariantCodes('gift_card_2', [GiftCardDeliveryType::Physical]));
        self::assertSame(
            $factory->getVariantCodes('gift_card_2', [GiftCardDeliveryType::Physical]),
            array_keys($this->variants($factory->create('gift_card_2', 'Gift card', deliveryTypes: [GiftCardDeliveryType::Physical]))),
        );
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

        // The variant names are what the customer chooses between on the product page, in their own language
        $names = [
            'virtual' => ['en_US' => 'Virtual — delivered by email', 'da_DK' => 'Virtuelt — leveres på email'],
            'physical' => ['en_US' => 'Physical — shipped to you', 'da_DK' => 'Fysisk — sendes til dig'],
        ];
        foreach ($names as $value => $nameByLocale) {
            $variant = $variants['gift_card_' . $value];
            self::assertSame(['gift_card_delivery_' . $value], array_values(array_map(
                static fn ($optionValue): ?string => $optionValue->getCode(),
                $variant->getOptionValues()->toArray(),
            )));
            foreach ($nameByLocale as $localeCode => $name) {
                self::assertSame($name, $variant->getTranslation($localeCode)->getName());
            }
        }
    }

    /** @test */
    public function it_creates_the_delivery_option_when_the_shop_has_none_yet(): void
    {
        // persisted for the caller's flush to write with the product, not flushed here
        $this->manager->persist(Argument::type(ProductOptionInterface::class))->shouldBeCalledOnce();

        $product = $this->factory()->create('gift_card', 'Gift card');

        $options = $product->getOptions()->toArray();
        self::assertCount(1, $options);

        $option = reset($options);
        self::assertInstanceOf(ProductOptionInterface::class, $option);
        self::assertSame('gift_card_delivery', $option->getCode());
        // The cart and the order show the option and its value, so both are named in the language of each locale
        self::assertSame('Delivery type', $option->getTranslation('en_US')->getName());
        self::assertSame('Leveringstype', $option->getTranslation('da_DK')->getName());

        $values = [];
        $danishValues = [];
        foreach ($option->getValues() as $value) {
            $values[(string) $value->getCode()] = $value->getTranslation('en_US')->getValue();
            $danishValues[(string) $value->getCode()] = $value->getTranslation('da_DK')->getValue();
        }
        // Option value codes are unique across all options, so the values are prefixed with the option's code rather
        // than risk the "physical" of a shop's own option
        self::assertSame(['gift_card_delivery_virtual' => 'Virtual', 'gift_card_delivery_physical' => 'Physical'], $values);
        self::assertSame(['gift_card_delivery_virtual' => 'Virtuelt', 'gift_card_delivery_physical' => 'Fysisk'], $danishValues);
    }

    /**
     * Every gift card product shares the option, so a second product reuses it instead of adding another option
     * with the same code
     *
     * @test
     */
    public function it_reuses_the_delivery_option_the_shop_already_has(): void
    {
        $existing = $this->deliveryOption('gift_card_delivery_virtual', 'gift_card_delivery_physical');
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn($existing);
        $this->manager->persist(Argument::any())->shouldNotBeCalled();
        $this->productOptionFactory->createNew()->shouldNotBeCalled();

        $product = $this->factory()->create('gift_card_2', 'Gift card');

        self::assertSame([$existing], array_values($product->getOptions()->toArray()));

        $variants = $this->variants($product);
        self::assertSame(['gift_card_2_virtual', 'gift_card_2_physical'], array_keys($variants));
        self::assertSame(['gift_card_delivery_virtual'], $this->optionValueCodes($variants['gift_card_2_virtual']));
        self::assertSame(['gift_card_delivery_physical'], $this->optionValueCodes($variants['gift_card_2_physical']));
    }

    /**
     * A shop that created its delivery option before the values got codes of their own has values coded with the bare
     * delivery type. Its option is used as it is, and the variants are named as they always were
     *
     * @test
     */
    public function it_reuses_a_delivery_option_whose_values_are_coded_with_the_bare_delivery_type(): void
    {
        $existing = $this->deliveryOption('virtual', 'physical');
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn($existing);
        $this->manager->persist(Argument::any())->shouldNotBeCalled();
        $this->productOptionFactory->createNew()->shouldNotBeCalled();

        $variants = $this->variants($this->factory()->create('gift_card_2', 'Gift card'));

        self::assertSame(['gift_card_2_virtual', 'gift_card_2_physical'], array_keys($variants));
        self::assertSame(['virtual'], $this->optionValueCodes($variants['gift_card_2_virtual']));
        self::assertSame(['physical'], $this->optionValueCodes($variants['gift_card_2_physical']));
    }

    /**
     * Each delivery type is looked up on its own: a value with the prefixed code is used when the option has one, and
     * a value with the bare code otherwise
     *
     * @test
     */
    public function it_prefers_a_value_with_the_prefixed_code_over_one_with_the_bare_code(): void
    {
        $this->productOptionRepository
            ->findOneBy(['code' => 'gift_card_delivery'])
            ->willReturn($this->deliveryOption('virtual', 'physical', 'gift_card_delivery_physical'))
        ;

        $variants = $this->variants($this->factory()->create('gift_card', 'Gift card'));

        self::assertSame(['virtual'], $this->optionValueCodes($variants['gift_card_virtual']));
        self::assertSame(['gift_card_delivery_physical'], $this->optionValueCodes($variants['gift_card_physical']));
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
        $this->productOptionRepository->findOneBy(['code' => 'gift_card_delivery'])->willReturn($this->deliveryOption('gift_card_delivery_virtual'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The product option "gift_card_delivery" has no value "gift_card_delivery_physical" (or "physical")');

        $this->factory()->create('gift_card', 'Gift card');
    }

    /**
     * The option is only persisted, and a repository lookup only finds what is flushed, so a second product created
     * before the flush (the product fixture creates several in one unit of work) gets the option the first one got
     * instead of a second option with the same, unique, code
     *
     * @test
     */
    public function it_reuses_the_delivery_option_it_created_until_it_is_flushed(): void
    {
        $this->productOptionFactory->createNew()->will(static fn (): ProductOption => new ProductOption())->shouldBeCalledOnce();

        $factory = $this->factory();

        $first = $factory->create('gift_card', 'Gift card');
        $second = $factory->create('gift_card_2', 'Gift card');

        self::assertSame(array_values($first->getOptions()->toArray()), array_values($second->getOptions()->toArray()));
        self::assertCount(1, $this->persisted);
    }

    /**
     * An option the entity manager no longer holds (it was cleared, say, without a flush) was never written, so the
     * factory creates the option again rather than hand out one nothing will save
     *
     * @test
     */
    public function it_creates_the_delivery_option_again_when_the_one_it_created_was_not_kept(): void
    {
        $factory = $this->factory();

        $first = $factory->create('gift_card', 'Gift card');
        $this->persisted = [];
        $second = $factory->create('gift_card_2', 'Gift card');

        self::assertNotSame(array_values($first->getOptions()->toArray()), array_values($second->getOptions()->toArray()));
        self::assertCount(1, $this->persisted);
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

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->willReturn($this->manager->reveal());

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
            $this->translator(),
            $managerRegistry->reveal(),
        );
    }

    /**
     * The plugin's own translations, so the names are the ones a shop gets
     */
    private function translator(): Translator
    {
        $translator = new Translator('en_US');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['en', 'da'] as $locale) {
            $translator->addResource('yaml', sprintf('%s/../../../src/Resources/translations/messages.%s.yml', __DIR__, $locale), $locale);
        }

        return $translator;
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

    /**
     * @return list<string>
     */
    private function optionValueCodes(ProductVariantInterface $variant): array
    {
        return array_values(array_map(
            static fn ($optionValue): string => (string) $optionValue->getCode(),
            $variant->getOptionValues()->toArray(),
        ));
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
