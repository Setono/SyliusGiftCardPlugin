<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

/**
 * The PDF carries a live code, so it is an admin download: named after the card so a merchant printing several can
 * tell them apart, and never handed to anyone who is not signed in
 */
final class DownloadGiftCardPdfActionTest extends AdminFunctionalTestCase
{
    /** @test */
    public function it_downloads_the_gift_card_as_a_pdf_named_after_its_code(): void
    {
        $this->logInAsAdministrator();
        $giftCard = $this->persistGiftCard('PRINTME', 5000);

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d/pdf', (int) $giftCard->getId()));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="gift-card-PRINTME.pdf"', $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $this->logInAsAdministrator();

        self::assertSame(404, $this->request('GET', '/admin/gift-cards/987654/pdf')->getStatusCode());
    }

    /** @test */
    public function it_does_not_hand_the_pdf_to_a_visitor_who_is_not_signed_in(): void
    {
        $giftCard = $this->persistGiftCard('PRINTME', 5000);

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d/pdf', (int) $giftCard->getId()));

        self::assertTrue($response->isRedirect('http://localhost/admin/login'));
    }
}
