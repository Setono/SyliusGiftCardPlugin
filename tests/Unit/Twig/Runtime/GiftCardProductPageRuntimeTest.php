<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\InitialGiftCardAmountResolverInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardProductPageRuntime;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

final class GiftCardProductPageRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<GiftCardCurrencyProviderInterface> */
    private ObjectProphecy $currencyProvider;

    /** @var ObjectProphecy<GiftCardDesignProviderInterface> */
    private ObjectProphecy $designProvider;

    /** @var ObjectProphecy<ProductVariantResolverInterface> */
    private ObjectProphecy $variantResolver;

    /** @var ObjectProphecy<InitialGiftCardAmountResolverInterface> */
    private ObjectProphecy $initialAmountResolver;

    private ChannelInterface $channel;

    private ProductInterface $product;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
        $this->product = $this->prophesize(ProductInterface::class)->reveal();

        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($this->channel);

        $this->currencyProvider = $this->prophesize(GiftCardCurrencyProviderInterface::class);
        $this->currencyProvider->getCurrencyCode($this->channel)->willReturn('EUR');

        $this->designProvider = $this->prophesize(GiftCardDesignProviderInterface::class);
        $this->variantResolver = $this->prophesize(ProductVariantResolverInterface::class);
        $this->initialAmountResolver = $this->prophesize(InitialGiftCardAmountResolverInterface::class);
    }

    /**
     * The design field preselects the channel's first design, and the amount field starts at what the resolver suggests
     * for the variant Sylius puts on a new cart line
     *
     * @test
     */
    public function it_starts_the_preview_in_the_first_design_with_the_amount_the_field_starts_at(): void
    {
        $variant = $this->prophesize(ProductVariantInterface::class)->reveal();
        $this->variantResolver->getVariant($this->product)->willReturn($variant);
        $this->designProvider->getDesigns($this->channel)->willReturn([
            $this->design('ab/cd/first.png'),
            $this->design('ef/gh/second.png'),
        ]);
        $this->initialAmountResolver->resolve($this->channel, 'EUR', $variant)->willReturn(2500);

        $preview = $this->createRuntime()->getPreview($this->product);

        self::assertSame('ab/cd/first.png', $preview->frontImagePath);
        self::assertSame(2500, $preview->amount);
        self::assertSame('EUR', $preview->currencyCode);
    }

    /** @test */
    public function it_starts_the_preview_without_an_image_when_the_channel_has_no_design(): void
    {
        $this->variantResolver->getVariant($this->product)->willReturn(null);
        $this->designProvider->getDesigns($this->channel)->willReturn([]);
        $this->initialAmountResolver->resolve($this->channel, 'EUR', null)->willReturn(null);

        $preview = $this->createRuntime()->getPreview($this->product);

        self::assertNull($preview->frontImagePath);
        self::assertNull($preview->amount);
        self::assertSame('EUR', $preview->currencyCode);
    }

    /** @test */
    public function it_starts_the_preview_without_an_image_when_the_first_design_has_none(): void
    {
        $this->variantResolver->getVariant($this->product)->willReturn(null);
        $this->designProvider->getDesigns($this->channel)->willReturn([$this->design(null)]);
        $this->initialAmountResolver->resolve($this->channel, 'EUR', null)->willReturn(null);

        self::assertNull($this->createRuntime()->getPreview($this->product)->frontImagePath);
    }

    /** @test */
    public function it_starts_the_preview_blank_outside_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());

        $preview = $this->createRuntime()->getPreview($this->product);

        self::assertNull($preview->frontImagePath);
        self::assertNull($preview->amount);
        self::assertNull($preview->currencyCode);
    }

    private function design(?string $frontImagePath): GiftCardDesignInterface
    {
        $design = $this->prophesize(GiftCardDesignInterface::class);
        if (null === $frontImagePath) {
            $design->getFrontImage()->willReturn(null);

            return $design->reveal();
        }

        $image = $this->prophesize(GiftCardDesignImageInterface::class);
        $image->getPath()->willReturn($frontImagePath);
        $design->getFrontImage()->willReturn($image->reveal());

        return $design->reveal();
    }

    private function createRuntime(): GiftCardProductPageRuntime
    {
        return new GiftCardProductPageRuntime(
            $this->channelContext->reveal(),
            $this->currencyProvider->reveal(),
            $this->designProvider->reveal(),
            $this->variantResolver->reveal(),
            $this->initialAmountResolver->reveal(),
        );
    }
}
