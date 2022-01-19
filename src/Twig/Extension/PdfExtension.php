<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class PdfExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ssgc_replace_css_option', [PdfRuntime::class, 'replaceCssOptions']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ssgc_get_pdf_options_hint', [PdfRuntime::class, 'getOptionsHint']),
        ];
    }
}
