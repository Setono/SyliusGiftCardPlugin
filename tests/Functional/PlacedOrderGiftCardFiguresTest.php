<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * What the gift cards cover and what remains to pay are what a shop prints wherever it sums up an order, after checkout
 * as well: in the order confirmation email, on the thank you page and in the customer's account. Placing the order
 * takes what the cards pay off their balances, so from then on the balances no longer tell what the cards paid, and the
 * figures have to come from the gift card payments the order was placed with (#498)
 */
final class PlacedOrderGiftCardFiguresTest extends OrderLifecycleTestCase
{
    private const FIGURES = '{{ setono_gift_card_covered_amount(order) }}|{{ setono_gift_card_remaining_total(order) }}|{{ setono_gift_card_coverage(order).total }}|{{ setono_gift_card_covered_amount_by_gift_card(order, order.giftCards.first) }}';

    /**
     * @test
     *
     * @dataProvider orders
     */
    public function the_figures_of_a_placed_order_are_what_its_gift_card_paid(int $balance, int $total, int $covered, bool $inALaterRequest): void
    {
        $giftCard = $this->createEnabledGiftCard('FIGURES000000001', $balance);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', $total);
        $order->addGiftCard($giftCard);
        $remaining = $total - $covered;
        if ($remaining > 0) {
            $this->selectPayment($order, $remaining);
        }
        $this->manager->flush();

        self::assertSame($this->figures($covered, $remaining), $this->render($order), 'precondition: the cart shows what the card covers');

        $this->placeOrder($order);
        self::assertSame($balance - $covered, $this->persistedBalanceOf($giftCard), 'precondition: placing the order took what the card paid');

        if ($inALaterRequest) {
            $order = $this->reload($order);
        }

        self::assertSame($this->figures($covered, $remaining), $this->render($order));
    }

    /**
     * @return iterable<string, array{int, int, int, bool}>
     */
    public static function orders(): iterable
    {
        foreach (['in the request that placed it' => false, 'in a later request' => true] as $when => $inALaterRequest) {
            // the issue's order: the card is spent, and nothing would be covered by its balance
            yield 'a card paying part of the order, ' . $when => [3000, 14077, 3000, $inALaterRequest];

            // the card keeps 5923, less than the order, so its balance would cover less than it paid
            yield 'a card paying the whole order, ' . $when => [20000, 14077, 14077, $inALaterRequest];
        }
    }

    /**
     * Sylius completes the checkout and creates the order before the plugin commits its redemption, and an application
     * may sum the order up in between, to export it, say. The card has paid nothing yet then, so the figures are what
     * its balance covers, which is what is about to be charged. The listener runs before CommitRedemptionSubscriber
     * (50); a winzou callback cannot be added at runtime, so this takes the Symfony Workflow adapter only
     *
     * @test
     */
    public function the_figures_of_an_order_created_but_not_yet_committed_are_what_its_gift_card_covers(): void
    {
        $this->useStateMachineAdapter(self::SYMFONY_WORKFLOW);

        $giftCard = $this->createEnabledGiftCard('FIGURES000000002', 3000);
        $order = $this->createCart();
        $this->addItem($order, 'MUG', 14077);
        $order->addGiftCard($giftCard);
        $this->selectPayment($order, 11077);
        $this->manager->flush();

        $figures = null;
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener('workflow.sylius_order.completed.create', function () use ($order, &$figures): void {
            $figures = $this->render($order);
        }, 100);

        $this->placeOrder($order);

        self::assertSame($this->figures(3000, 11077), $figures);
        self::assertSame($this->figures(3000, 11077), $this->render($order), 'precondition: the card paid what it covered');
    }

    private function render(Order $order): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->createTemplate(self::FIGURES)->render(['order' => $order]);
    }

    /**
     * What the gift cards cover, what remains to pay, the total of the coverage and what the one card covers
     */
    private function figures(int $covered, int $remaining): string
    {
        return sprintf('%d|%d|%d|%d', $covered, $remaining, $covered, $covered);
    }
}
