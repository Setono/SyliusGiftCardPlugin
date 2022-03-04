<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

final class DefaultPdfCssProvider implements DefaultPdfCssProviderInterface
{
    private string $defaultCssFilePath;

    public function __construct(string $defaultCssFilePath)
    {
        $this->defaultCssFilePath = $defaultCssFilePath;
    }

    public function getDefaultCss(): string
    {
        try {
            $file = new File($this->defaultCssFilePath);

            return $file->getContent();
        } catch (FileNotFoundException $e) {
            return '';
        }
    }
}
