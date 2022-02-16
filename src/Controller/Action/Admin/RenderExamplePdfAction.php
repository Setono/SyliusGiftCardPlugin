<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Gaufrette\Filesystem;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfPathGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RenderExamplePdfAction
{
    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardPdfPathGeneratorInterface $giftCardPdfPathGenerator;

    private Filesystem $filesystem;

    public function __construct(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardPdfPathGeneratorInterface $giftCardPdfPathGenerator,
        Filesystem $filesystem
    ) {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardPdfPathGenerator = $giftCardPdfPathGenerator;
        $this->filesystem = $filesystem;
    }

    public function __invoke(Request $request, int $id): PdfResponse
    {
        /** @var GiftCardConfigurationInterface|null $giftCardConfiguration */
        $giftCardConfiguration = $this->giftCardConfigurationRepository->find($id);
        if (null === $giftCardConfiguration) {
            throw new NotFoundHttpException('Gift card configuration not found');
        }
        $filePath = $this->giftCardPdfPathGenerator->generatePath($giftCardConfiguration);
        $response = new PdfResponse($this->filesystem->read($filePath), 'gift_card.pdf');

        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'gift_card.pdf'),
        ]);
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
