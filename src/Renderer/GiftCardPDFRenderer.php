<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Knp\Snappy\GeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class GiftCardPDFRenderer implements GiftCardPDFRendererInterface
{
    private Environment $twig;

    private GiftCardChannelConfigurationProviderInterface $configurationProvider;

    private ChannelContextInterface $channelContext;

    private LocaleContextInterface $localeContext;

    private GeneratorInterface $snappy;
    private PdfRenderingOptionsProviderInterface $renderingOptionsProvider;

    public function __construct(
        Environment $twig,
        GiftCardChannelConfigurationProviderInterface $configurationProvider,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        GeneratorInterface $snappy,
        PdfRenderingOptionsProviderInterface $renderingOptionsProvider
    ) {
        $this->twig = $twig;
        $this->configurationProvider = $configurationProvider;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
        $this->snappy = $snappy;
        $this->renderingOptionsProvider = $renderingOptionsProvider;
    }

    public function render(
        GiftCardInterface $giftCard,
        ChannelInterface $channel = null,
        string $localeCode = null
    ): PDFResponse {
        if (null === $channel) {
            $channel = $this->channelContext->getChannel();
        }

        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        $giftCardConfiguration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);

        $template = $giftCardConfiguration->getTemplate();
        Assert::notNull($template);

        $template = '{% extends "@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf_layout.html.twig" %}{% block content %}' . $template . '{% endblock %}';

        $html = $this->twig->render($this->twig->createTemplate($template), [
            'channel' => $channel,
            'localeCode' => $localeCode,
            'giftCard' => $giftCard,
        ]);

        $renderingOptions = $this->renderingOptionsProvider->getRenderingOptions($giftCardConfiguration);

        return new PDFResponse($this->snappy->getOutputFromHtml($html, $renderingOptions));
    }
}
