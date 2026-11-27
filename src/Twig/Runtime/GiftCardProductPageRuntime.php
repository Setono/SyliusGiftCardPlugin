<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardDesignProviderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\InitialGiftCardAmountResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardProductPageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
        private readonly GiftCardDesignProviderInterface $designProvider,
        private readonly ProductVariantResolverInterface $variantResolver,
        private readonly InitialGiftCardAmountResolverInterface $initialAmountResolver,
    ) {
    }

    /**
     * The add to cart form is rendered by a request of its own, so the page's preview works out how the form starts out
     * the way the form does: the channel's first design, which the design field preselects, and the amount the field
     * starts at for the variant Sylius preselects, the one it puts on a new cart line
     */
    public function getPreview(ProductInterface $product): GiftCardPreview
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return new GiftCardPreview(null, null, null);
        }

        $currencyCode = $this->currencyProvider->getCurrencyCode($channel);

        $design = $this->designProvider->getDesigns($channel)[0] ?? null;

        $variant = $this->variantResolver->getVariant($product);

        return new GiftCardPreview(
            $design?->getFrontImage()?->getPath(),
            $this->initialAmountResolver->resolve(
                $channel,
                $currencyCode,
                $variant instanceof ProductVariantInterface ? $variant : null,
            ),
            $currencyCode,
        );
    }
}
