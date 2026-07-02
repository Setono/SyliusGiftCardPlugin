<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Twig\Environment;

final class DompdfGiftCardPdfGenerator implements GiftCardPdfGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $template,
        private readonly string $pageSize,
        private readonly string $publicDir,
        private readonly string $mediaDir = 'media/image',
    ) {
    }

    public function generate(GiftCardInterface $giftCard): string
    {
        $html = $this->twig->render($this->template, [
            'giftCard' => $giftCard,
            'frontImagePath' => $this->resolveImagePath($giftCard->getDesign()?->getFrontImage()),
            'backImagePath' => $this->resolveImagePath($giftCard->getDesign()?->getBackImage()),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $this->publicDir);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($this->pageSize, 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
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
