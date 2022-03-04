<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class DefaultPdfCssProvider implements DefaultPdfCssProviderInterface
{
    private string $defaultCssFilePath;

    public function __construct(string $defaultCssFilePath)
    {
        $this->defaultCssFilePath = $defaultCssFilePath;
    }

    public function getDefaultCss(): string
    {
        $content = file_get_contents($this->defaultCssFilePath);

        if (false === $content) {
            return '';
        }

        return $content;
    }
}
