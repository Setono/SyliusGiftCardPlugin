<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PdfExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ssgc_get_base64_encoded_example_pdf_content', [PdfRuntime::class, 'getBase64EncodedExamplePdfContent']),
        ];
    }
}
