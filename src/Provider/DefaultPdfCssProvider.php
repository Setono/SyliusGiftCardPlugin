<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

final class DefaultPdfCssProvider implements DefaultPdfCssProviderInterface
{
    private string $defaultCssFilePath;

    public function __construct(string $defaultCssFilePath)
    {
        $this->defaultCssFilePath = $defaultCssFilePath;
    }

    public function getDefaultCss(): string
    {
        // TODO: When dropping Symfony 4.4, switch to Symfony File class
        $content = file_get_contents($this->defaultCssFilePath);

        if (false === $content) {
            return '';
        }

        return $content;
    }
}
