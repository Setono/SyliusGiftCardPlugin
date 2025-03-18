<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Sylius\Component\Core\Model\OrderInterface;
use Knp\Snappy\GeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class PdfRenderer implements PdfRendererInterface
{
    public function __construct(private readonly Environment $twig, private readonly GiftCardConfigurationProviderInterface $configurationProvider, private readonly ChannelContextInterface $channelContext, private readonly LocaleContextInterface $localeContext, private readonly GeneratorInterface $snappy, private readonly PdfRenderingOptionsProviderInterface $renderingOptionsProvider, private readonly NormalizerInterface $normalizer)
    {
    }

    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration = null,
        ChannelInterface $channel = null,
        string $localeCode = null,
    ): PdfResponse {
        if (!$channel instanceof ChannelInterface) {
            $order = $giftCard->getOrder();
            if ($order instanceof OrderInterface) {
                $channel = $order->getChannel();
            }

            if (!$channel instanceof ChannelInterface) {
                $channel = $this->channelContext->getChannel();
            }
        }

        if (null === $localeCode) {
            $order = $giftCard->getOrder();
            if ($order instanceof OrderInterface) {
                $localeCode = $order->getLocaleCode();
            }

            if (null === $localeCode) {
                $localeCode = $this->localeContext->getLocaleCode();
            }
        }

        if (!$giftCardConfiguration instanceof GiftCardConfigurationInterface) {
            $giftCardConfiguration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        }

        $template = $giftCardConfiguration->getTemplate();
        Assert::notNull($template);

        $html = $this->twig->render($this->twig->createTemplate($template), [
            'channel' => $this->normalizer->normalize($channel, null, ['groups' => 'setono:sylius-gift-card:render']),
            'localeCode' => $localeCode,
            'giftCard' => $this->normalizer->normalize($giftCard, null, [
                'groups' => 'setono:sylius-gift-card:render',
                'localeCode' => $localeCode,
            ]),
            'configuration' => $this->normalizer->normalize($giftCardConfiguration, null, [
                'groups' => 'setono:sylius-gift-card:render',
                'iri' => 'https://setono.com', // we add a fake IRI else we get the 'Unable to generate an IRI' exception when the gift card configuration hasn't been saved yet
            ]),
        ]);

        $renderingOptions = $this->renderingOptionsProvider->getRenderingOptions($giftCardConfiguration);

        return PdfResponse::fromGiftCard($this->snappy->getOutputFromHtml($html, $renderingOptions), $giftCard);
    }
}
