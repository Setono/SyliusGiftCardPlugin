<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\GeneratorInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;

final class AdminRenderPdfAction
{
    private GeneratorInterface $snappy;

    private string $publicDir;

    public function __construct(
        GeneratorInterface $snappy,
        string $publicDir
    ) {
        $this->snappy = $snappy;
        $this->publicDir = $publicDir;
    }

    public function __invoke(Request $request, int $id): PdfResponse
    {
        $filePath = \sprintf(
            '%s/gift_card_configuration_pdf_%d.pdf',
            $this->publicDir,
            $id
        );
        $response = new PdfResponse(\file_get_contents($filePath), 'gift_card.pdf');

        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'gift_card.pdf')
        ]);
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
