<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Twig\Environment;

final class DefaultPdfCssProvider implements DefaultPdfCssProviderInterface
{
    private string $defaultCssFile;

    private Environment $twig;

    public function __construct(string $defaultCssFile, Environment $twig)
    {
        $this->defaultCssFile = $defaultCssFile;
        $this->twig = $twig;
    }

    public function getDefaultCss(): string
    {
        return $this->twig->render($this->defaultCssFile);
    }
}
