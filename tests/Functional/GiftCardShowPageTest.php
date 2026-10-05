<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;

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

    /**
     * The ledger is the audit trail for the card's money: each row says which order it belongs to, linked to that
     * order, and which administrator made a movement by hand. The administrator is a copy of their user identifier,
     * not a reference to their account, so it is shown as text
     *
     * @test
     */
    public function it_shows_the_order_and_the_administrator_of_each_ledger_row(): void
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setNumber('000042');
        $this->manager->persist($order);

        $giftCard = $this->persistGiftCard('SHOWPAGELEDGER01', 5000);

        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = self::getContainer()->get(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard, $order);
        $balanceOperator->adjust($giftCard, -1000, 'Paid in the physical store', 'jane');
        $this->manager->flush();

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        $ledger = '//table[thead/tr/th[normalize-space() = "Created by"]]';
        self::assertSame(['Date', 'Type', 'Amount', 'Reason', 'Order', 'Created by'], self::textsOf($response, $ledger . '/thead/tr/th'));
        // type, order and administrator of each row, oldest first
        self::assertSame(['Issued', '#000042', '-'], self::textsOf($response, $ledger . '/tbody/tr[1]/td[position() = 2 or position() >= 5]'));
        self::assertSame(['Manual adjustment', '-', 'jane'], self::textsOf($response, $ledger . '/tbody/tr[2]/td[position() = 2 or position() >= 5]'));
        self::assertSame(
            [sprintf('/admin/orders/%d', (int) $order->getId())],
            self::textsOf($response, $ledger . '/tbody/tr[1]/td[5]/a/@href'),
        );
    }
}
