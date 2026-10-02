<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

/**
 * What the admin's gift card show page tells about a card
 */
final class GiftCardShowPageTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /**
     * The card shows the message with the line breaks it was written with, so the show page does too. It is still the
     * customer's text, so it stays escaped. A message stored before symfony/form 6.4.31 may hold CR LF, which is one
     * line break as well
     *
     * @test
     */
    public function it_shows_the_message_with_its_line_breaks(): void
    {
        $giftCard = $this->persistGiftCard('SHOWPAGEMESSAGE1', 5000);
        $giftCard->setCustomMessage("Happy birthday,\nlove from <b>Anna</b>\r\nand Bob");
        $this->manager->flush();

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        self::assertStringContainsString(
            "<td>Happy birthday,<br />\nlove from &lt;b&gt;Anna&lt;/b&gt;<br />\r\nand Bob</td>",
            (string) $response->getContent(),
        );
    }

    /** @test */
    public function it_shows_a_dash_for_a_card_without_a_message(): void
    {
        $giftCard = $this->persistGiftCard('SHOWPAGEMESSAGE2', 5000);
        $giftCard->setCustomMessage(null);
        $this->manager->flush();

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        self::assertSame(['-'], self::textsOf($response, '//tr[td[1]/strong[normalize-space() = "Custom message"]]/td[2]'));
    }
}
