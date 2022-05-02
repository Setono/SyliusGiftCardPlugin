<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Provider\PdfAvailableCssOptionProviderInterface;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\RuntimeExtensionInterface;
use function twig_replace_filter;
use Webmozart\Assert\Assert;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private PdfAvailableCssOptionProviderInterface $cssOptionProvider;

    private Environment $twig;

    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private GiftCardFactoryInterface $giftCardFactory;

    public function __construct(
        PdfAvailableCssOptionProviderInterface $cssOptionProvider,
        Environment $twig,
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        GiftCardFactoryInterface $giftCardFactory
    ) {
        $this->cssOptionProvider = $cssOptionProvider;
        $this->twig = $twig;
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function replaceCssOptions(string $rawCss, array $twigContext): string
    {
        $options = $this->cssOptionProvider->getOptionsValue($twigContext);

        try {
            /** @psalm-suppress UndefinedFunction */
            $replacedCss = twig_replace_filter($rawCss, $options);
            Assert::string($replacedCss);

            return $replacedCss;
        } catch (RuntimeError $e) {
            return $rawCss;
        }
    }

    public function getOptionsHint(): array
    {
        return $this->cssOptionProvider->getOptionsHint();
    }

    public function getPdfTemplateContent(): string
    {
        $templateWrapper = $this->twig->load('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf_layout.html.twig');
        \preg_match(
            '/<body(.|\n)*>(.|\n)*<\/body>/',
            $templateWrapper->getSourceContext()->getCode(),
            $bodyContent
        );

        $domCrawler = new Crawler($bodyContent[0]);

        return $domCrawler->filter('html > body')->html();
    }

    public function getBase64EncodedExamplePdfContent(GiftCardConfigurationInterface $giftCardChannelConfiguration): string
    {
        $giftCard = $this->giftCardFactory->createExample();

        $content = $this->giftCardPdfGenerator->generateAndGetContent($giftCard, $giftCardChannelConfiguration);

        return \base64_encode($content);
    }
}
