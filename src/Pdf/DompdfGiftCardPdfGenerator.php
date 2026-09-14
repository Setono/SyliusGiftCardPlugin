<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Pdf;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Twig\Environment;

final class DompdfGiftCardPdfGenerator implements GiftCardPdfGeneratorInterface
{
    /**
     * The width in CSS pixels of the grid the card templates are laid out on. It is A6 landscape as a browser
     * would see it - 419.53pt at 96 dpi - because that is the size the layout was drawn for
     */
    private const DESIGN_WIDTH = 560.0;

    /**
     * Dompdf lays CSS pixels out at 96 to the inch and the paper sizes are in points, 72 to the inch
     */
    private const PIXELS_PER_POINT = 96 / 72;

    public function __construct(
        private readonly Environment $twig,
        private readonly string $template,
        private readonly string $pageSize,
        private readonly string $publicDir,
        private readonly string $mediaDir = 'media/image',
        /**
         * Dompdf only ever receives absolute paths below the chrooted public dir, so remote resource loading is
         * off unless a host explicitly opts in (for example to render design images served from a CDN).
         */
        private readonly bool $remoteEnabled = false,
    ) {
    }

    public function generate(GiftCardInterface $giftCard): string
    {
        $paper = $this->resolvePaper();

        // The card is laid out on a fixed pixel grid, so on its own it would sit in a corner of anything bigger
        // than the size it was drawn for. The template scales the whole card onto the page with a CSS transform,
        // so the page size decides the scale and nothing else in the layout has to know about it. Sizes with
        // another aspect ratio than the design's gain or lose height, which the layout absorbs: the blocks are
        // anchored to the top and the bottom of the card, never sized against each other
        $pageWidth = ($paper[2] - $paper[0]) * self::PIXELS_PER_POINT;
        $pageHeight = ($paper[3] - $paper[1]) * self::PIXELS_PER_POINT;
        $scale = $pageWidth / self::DESIGN_WIDTH;

        $html = $this->twig->render($this->template, [
            'giftCard' => $giftCard,
            'localeCode' => $this->resolveLocaleCode($giftCard),
            'frontImagePath' => $this->resolveImagePath($giftCard->getDesign()?->getFrontImage()),
            'backImagePath' => $this->resolveImagePath($giftCard->getDesign()?->getBackImage()),
            'pageWidth' => $pageWidth,
            'pageHeight' => $pageHeight,
            'cardWidth' => self::DESIGN_WIDTH,
            'cardHeight' => $pageHeight / $scale,
            'scale' => $scale,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', $this->remoteEnabled);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $this->publicDir);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($paper);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Resolves the configured page size to its dimensions in points, in landscape. Dompdf itself falls back to
     * letter for a size it does not know, and the scale has to be derived from the same dimensions it draws on
     *
     * @return array{float, float, float, float}
     */
    private function resolvePaper(): array
    {
        /** @var array<string, array{float|int, float|int, float|int, float|int}> $sizes */
        $sizes = CPDF::$PAPER_SIZES;

        $size = $sizes[strtolower($this->pageSize)] ?? $sizes['letter'];

        $width = (float) $size[2] - (float) $size[0];
        $height = (float) $size[3] - (float) $size[1];

        return [0.0, 0.0, max($width, $height), min($width, $height)];
    }

    /**
     * The PDF is rendered outside a request (order confirmation, admin resend), so the locale cannot be
     * taken from the locale context. Use the locale the card was bought in, falling back to the channel
     * default; the template falls back to the same locale Sylius' money formatter uses when it gets none.
     */
    private function resolveLocaleCode(GiftCardInterface $giftCard): ?string
    {
        return $giftCard->getOrder()?->getLocaleCode()
            ?? $giftCard->getChannel()?->getDefaultLocale()?->getCode();
    }

    private function resolveImagePath(?GiftCardDesignImageInterface $image): ?string
    {
        $path = $image?->getPath();
        if (null === $path) {
            return null;
        }

        $absolute = rtrim($this->publicDir, '/') . '/' . $this->mediaDir . '/' . ltrim($path, '/');

        return is_file($absolute) ? $absolute : null;
    }
}
