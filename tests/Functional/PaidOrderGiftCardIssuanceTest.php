<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Paying the order is what turns the gift cards it bought into money the shop owes: they are enabled, their issuance
 * goes into the ledger, and the buyer gets them in one email with a PDF of each card attached
 */
final class PaidOrderGiftCardIssuanceTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function paying_the_order_issues_the_gift_cards_it_bought_and_emails_them_together(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        // Two units of a virtual gift card: add to cart created the card of the first, raising the quantity in the
        // cart added the second without one, which placing the order gives it
        $order = $this->createCart();
        $item = $this->addItem($order, 'GIFT_CARD', 5000, giftCard: true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $unit->setGiftCard($this->createPendingGiftCard('BOUGHT0000000001'));
        new OrderItemUnit($item);
        $cash = $this->selectPayment($order, 10000);
        $this->manager->flush();

        $this->placeOrder($order);

        $giftCards = $this->giftCardsOf($item);
        self::assertCount(2, $giftCards, 'precondition: placing the order gave every unit its card');
        foreach ($giftCards as $giftCard) {
            self::assertFalse($giftCard->isEnabled(), 'precondition: a card is not issued before the order is paid');
        }
        self::assertSame([], $this->sentEmails(), 'precondition: nothing is sent before the order is paid');

        $this->apply($cash, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());

        $attachments = [];
        foreach ($giftCards as $giftCard) {
            $code = (string) $giftCard->getCode();

            self::assertTrue($giftCard->isEnabled(), sprintf('%s should have been enabled', $code));
            self::assertSame(
                [['type' => GiftCardTransactionInterface::TYPE_ISSUE, 'amount' => 5000, 'idempotencyKey' => 'issue-' . $code, 'order' => null, 'payment' => null]],
                $this->persistedLedgerOf($giftCard),
                sprintf('the issuance of %s should have been recorded once', $code),
            );

            $attachments[] = sprintf('gift-card-%s.pdf', $code);
        }

        $emails = $this->sentEmails();
        self::assertCount(1, $emails, 'the cards should have been sent in one email');
        self::assertSame($order->getCustomer()?->getEmail(), $emails[0]->getTo()[0]->getAddress(), 'the cards should have been sent to the buyer');

        $attached = array_map(static function (DataPart $attachment): string {
            self::assertSame('application/pdf', $attachment->getContentType());

            return (string) $attachment->getFilename();
        }, $emails[0]->getAttachments());
        sort($attached);
        sort($attachments);
        self::assertSame($attachments, $attached, 'every card should have been attached to the email');
    }

    /**
     * The emails the application actually delivered, rather than queued
     *
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $emails = [];
        foreach (self::getMailerEvents() as $event) {
            $message = $event->getMessage();
            if (!$event->isQueued() && $message instanceof Email) {
                $emails[] = $message;
            }
        }

        return $emails;
    }

    /**
     * @return list<GiftCardInterface>
     */
    private function giftCardsOf(OrderItem $item): array
    {
        $giftCards = [];
        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            if (null !== $giftCard) {
                $giftCards[] = $giftCard;
            }
        }

        return $giftCards;
    }
}
