<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Provider\PdfAvailableCssOptionProviderInterface;
use Twig\Extension\RuntimeExtensionInterface;
use function twig_replace_filter;

final class PdfRuntime implements RuntimeExtensionInterface
{
    private PdfAvailableCssOptionProviderInterface $cssOptionProvider;

    public function __construct(PdfAvailableCssOptionProviderInterface $cssOptionProvider)
    {
        $this->cssOptionProvider = $cssOptionProvider;
    }

    public function replaceCssOptions(string $rawCss, array $twigContext): string
    {
        $options = $this->cssOptionProvider->getOptionsValue($twigContext);

        return twig_replace_filter($rawCss, $options);
    }

    public function getOptionsHint(): array
    {
        return $this->cssOptionProvider->getOptionsHint();
    }
}
