<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

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
    private Environment $twig;

    private GiftCardConfigurationProviderInterface $configurationProvider;

    private ChannelContextInterface $channelContext;

    private LocaleContextInterface $localeContext;

    private GeneratorInterface $snappy;

    private PdfRenderingOptionsProviderInterface $renderingOptionsProvider;

    private NormalizerInterface $normalizer;

    public function __construct(
        Environment $twig,
        GiftCardConfigurationProviderInterface $configurationProvider,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        GeneratorInterface $snappy,
        PdfRenderingOptionsProviderInterface $renderingOptionsProvider,
        NormalizerInterface $normalizer
    ) {
        $this->twig = $twig;
        $this->configurationProvider = $configurationProvider;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
        $this->snappy = $snappy;
        $this->renderingOptionsProvider = $renderingOptionsProvider;
        $this->normalizer = $normalizer;
    }

    public function render(
        GiftCardInterface $giftCard,
        GiftCardConfigurationInterface $giftCardConfiguration = null,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): PdfResponse {
        if (null === $channel) {
            $order = $giftCard->getOrder();
            if (null !== $order) {
                $channel = $order->getChannel();
            }

            if (null === $channel) {
                $channel = $this->channelContext->getChannel();
            }
        }

        if (null === $localeCode) {
            $order = $giftCard->getOrder();
            if (null !== $order) {
                $localeCode = $order->getLocaleCode();
            }

            if (null === $localeCode) {
                $localeCode = $this->localeContext->getLocaleCode();
            }
        }

        if (null === $giftCardConfiguration) {
            $giftCardConfiguration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        }

        $template = $giftCardConfiguration->getTemplate();
        Assert::notNull($template);

        $template = '{% extends "@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf_layout.html.twig" %}{% block content %}' . $template . '{% endblock %}';

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

        return new PdfResponse($this->snappy->getOutputFromHtml($html, $renderingOptions));
    }
}
