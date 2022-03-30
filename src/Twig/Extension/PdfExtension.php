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
            new TwigFunction('ssgc_get_pdf_template_content', [PdfRuntime::class, 'getPdfTemplateContent']),
            new TwigFunction('ssgc_get_base64_encoded_example_pdf_content', [PdfRuntime::class, 'getBase64EncodedExamplePdfContent']),
        ];
    }
}
