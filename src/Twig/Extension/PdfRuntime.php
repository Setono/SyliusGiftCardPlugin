<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

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

    public function __construct(
        PdfAvailableCssOptionProviderInterface $cssOptionProvider,
        Environment $twig
    ) {
        $this->cssOptionProvider = $cssOptionProvider;
        $this->twig = $twig;
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
        $templateWrapper = $this->twig->load('@SetonoSyliusGiftCardPlugin/Shop/GiftCard/pdf.html.twig');
        \preg_match(
            '/<body(.|\n)*>(.|\n)*<\/body>/',
            $templateWrapper->getSourceContext()->getCode(),
            $bodyContent
        );

        $domCrawler = new Crawler($bodyContent[0]);

        return $domCrawler->filter('html > body')->html();
    }
}
