<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

interface BarcodeGeneratorInterface
{
    /**
     * Returns an image data URI (e.g. "data:image/svg+xml;base64,...") picturing the given value, ready to be used
     * as the src of an img element, or null when the value cannot be pictured.
     *
     * A data URI rather than a URL or a file path on purpose: the gift card PDF is rendered by dompdf, which
     * neither renders inline svg elements nor should need remote or file system access to draw a barcode.
     *
     * Replace or decorate the default (Code 128) implementation to picture the code differently, for example as a
     * QR code pointing at a balance page
     */
    public function generate(string $value): ?string;
}
