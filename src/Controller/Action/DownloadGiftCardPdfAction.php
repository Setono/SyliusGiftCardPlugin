<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadGiftCardPdfAction
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly GiftCardPdfGeneratorInterface $pdfGenerator,
    ) {
    }

    public function __invoke(int $id): Response
    {
        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException();
        }

        return new Response($this->pdfGenerator->generate($giftCard), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="gift-card-%s.pdf"', (string) $giftCard->getCode()),
        ]);
    }
}
