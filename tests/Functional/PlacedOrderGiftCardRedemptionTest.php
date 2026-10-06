<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Placing an order is what redeems the gift cards applied to it: each becomes a completed payment against the order,
 * and its balance is taken through the ledger in a row keyed on the order and the card. The key, and the payment the
 * card already made, are what make the redemption safe to run again for the same order
 */
final class PlacedOrderGiftCardRedemptionTest extends OrderLifecycleTestCase
{
    /**
     * @test
     *
     * @dataProvider adapters
     */
    public function placing_the_order_pays_it_with_a_completed_payment_per_gift_card(string $adapter): void
    {
        $this->useStateMachineAdapter($adapter);

        // the first card is spent on the order, the second pays the rest and keeps 5000
        $spent = $this->createEnabledGiftCard('PLACED0000000001', 6000);
        $kept = $this->createEnabledGiftCard('PLACED0000000002', 9000);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 10000);
        $order->addGiftCard($spent);
        $order->addGiftCard($kept);
        $this->manager->flush();

        $this->placeOrder($order);

        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame(10000, $order->getTotal(), 'a gift card pays for the order rather than discounting it');
        self::assertCount(2, $order->getPayments(), 'the gift cards should be the only payments');

        self::assertSame(0, $this->persistedBalanceOf($spent));
        self::assertSame(5000, $this->persistedBalanceOf($kept));
        $this->assertRedeemedOnce($order, $spent, 6000);
        $this->assertRedeemedOnce($order, $kept, 4000);
    }

    /**
     * The redemption can be asked for again for an order that is already placed: before anything of the placement is
     * flushed, where the ledger row keyed for it is not in the database yet, or in a later request. The card still
     * has a balance that would cover the order all over again, so it is the payment the card already made that keeps
     * it from paying twice
     *
     * @test
     *
     * @dataProvider replays
     */
    public function redeeming_the_gift_cards_of_a_placed_order_again_takes_nothing_more(bool $inALaterRequest): void
    {
        $giftCard = $this->createEnabledGiftCard('REPLAYED00000001', 15000);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 10000);
        $order->addGiftCard($giftCard);
        $this->manager->flush();

        $this->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        self::assertSame(5000, $giftCard->getAmount(), 'precondition: the card kept a balance');

        if ($inALaterRequest) {
            $this->manager->flush();
            $order = $this->reload($order);
        }

        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');
        $redemptionMethod->commit($order);
        $this->manager->flush();

        self::assertCount(1, $this->giftCardPayments($order));
        self::assertSame(5000, $this->persistedBalanceOf($giftCard));
        $this->assertRedeemedOnce($order, $giftCard, 10000);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function replays(): iterable
    {
        yield 'before the placement is flushed' => [false];
        yield 'in a later request' => [true];
    }

    /**
     * The card's ledger holds exactly one row: the redemption, keyed on the order and the card, pointing at the order
     * and at the completed gift card payment that carried the amount
     */
    private function assertRedeemedOnce(Order $order, GiftCardInterface $giftCard, int $amount): void
    {
        $ledger = $this->persistedLedgerOf($giftCard);
        self::assertCount(1, $ledger, sprintf('%s should have been redeemed exactly once', (string) $giftCard->getCode()));

        [$redemption] = $ledger;
        self::assertSame(GiftCardTransactionInterface::TYPE_REDEEM, $redemption['type']);
        self::assertSame(-$amount, $redemption['amount']);
        self::assertSame(sprintf('redeem:order:%d:gift_card:%d', (int) $order->getId(), (int) $giftCard->getId()), $redemption['idempotencyKey']);
        self::assertSame($order->getId(), $redemption['order']);

        $payment = $this->paymentWithId($order, $redemption['payment']);
        self::assertContains($payment, $this->giftCardPayments($order), 'the redemption should point at a gift card payment');
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame($amount, $payment->getAmount());
    }

    private function paymentWithId(Order $order, ?int $id): PaymentInterface
    {
        foreach ($order->getPayments() as $payment) {
            if ($payment->getId() === $id) {
                self::assertInstanceOf(PaymentInterface::class, $payment);

                return $payment;
            }
        }

        self::fail(sprintf('the order carries no payment with the id %s', var_export($id, true)));
    }
}
