<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\DownloadGiftCardPdfAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadGiftCardPdfActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * A merchant printing several cards saves each as its own file, so the file is named after the card
     *
     * @test
     */
    public function it_offers_the_pdf_of_the_gift_card_as_a_download_named_after_its_code(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('PRINTME');

        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(42)->willReturn($giftCard);

        $pdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $pdfGenerator->generate($giftCard)->willReturn('%PDF-gift-card');

        $response = (new DownloadGiftCardPdfAction($giftCardRepository->reveal(), $pdfGenerator->reveal()))(42);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('%PDF-gift-card', $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="gift-card-PRINTME.pdf"', $response->headers->get('Content-Disposition'));
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->find(42)->willReturn(null);

        $pdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $pdfGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(NotFoundHttpException::class);

        (new DownloadGiftCardPdfAction($giftCardRepository->reveal(), $pdfGenerator->reveal()))(42);
    }
}
