<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cards an order bought are enabled when the order is paid and disabled when the payment is refunded in full. The
 * operator does both as a state machine callback and flushes nothing itself: whatever applies the transition flushes
 * afterwards. Here that is the admin, pressing Sylius' own buttons, and the cards are read back from the database
 * once the request is over
 */
final class OrderGiftCardAdminTransitionsTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /** @test */
    public function completing_the_payment_in_the_admin_enables_the_gift_cards_the_order_bought(): void
    {
        [$orderId, $paymentId] = $this->placeOrderBuyingAGiftCard('ADMINPAID0000001');

        $response = $this->press($orderId, sprintf('/admin/orders/%d/payments/%d/complete', $orderId, $paymentId));
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));

        $this->manager->clear();

        $order = $this->manager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $order);
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());

        $giftCard = $this->findGiftCard('ADMINPAID0000001');
        self::assertTrue($giftCard->isEnabled(), 'paying the order should have enabled the card it bought');
        // The issuance leads back to the order that paid for the card. The administrator only said the order was
        // paid, so they are not named as having issued the card: the order did
        self::assertSame(
            [[GiftCardTransactionInterface::TYPE_ISSUE, 5000, $orderId, null]],
            array_map(
                static fn (GiftCardTransactionInterface $transaction): array => [
                    $transaction->getType(),
                    $transaction->getAmount(),
                    $transaction->getOrder()?->getId(),
                    $transaction->getCreatedBy(),
                ],
                array_values($giftCard->getTransactions()->toArray()),
            ),
        );
    }

    /** @test */
    public function refunding_the_payment_in_the_admin_disables_the_gift_cards_the_order_bought(): void
    {
        [$orderId, $paymentId] = $this->placeOrderBuyingAGiftCard('ADMINREFUNDED001');

        $this->press($orderId, sprintf('/admin/orders/%d/payments/%d/complete', $orderId, $paymentId));
        $this->manager->clear();
        self::assertTrue($this->findGiftCard('ADMINREFUNDED001')->isEnabled(), 'precondition: paying enabled the card');

        $response = $this->press($orderId, sprintf('/admin/orders/%d/payments/%d/refund', $orderId, $paymentId));
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));

        $this->manager->clear();

        $order = $this->manager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $order);
        self::assertSame(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());

        self::assertFalse($this->findGiftCard('ADMINREFUNDED001')->isEnabled(), 'refunding the order in full should have disabled the card');
    }

    /**
     * Presses a button on the order's page in the admin the way a browser does: with the CSRF token the page renders
     * for it, as the PUT the form overrides its POST with
     */
    private function press(int $orderId, string $action): Response
    {
        $show = $this->request('GET', sprintf('/admin/orders/%d', $orderId));
        self::assertSame(200, $show->getStatusCode());

        return $this->request('POST', $action, [
            '_method' => 'PUT',
            '_csrf_token' => self::valueOf($show, sprintf('//form[@action="%s"]//input[@name="_csrf_token"]', $action)),
        ]);
    }

    /**
     * An order buying one virtual gift card, placed and awaiting its cash payment: the card was created disabled
     * when it went into the cart, and reconcile gave it its amount when the order was placed
     *
     * @return array{int, int} the ids of the order and of its payment
     */
    private function placeOrderBuyingAGiftCard(string $code): array
    {
        $customer = new Customer();
        $customer->setEmail(strtolower($code) . '@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $item = $this->addItem($order, 'GIFT_CARD_' . $code, 5000, giftCard: true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $unit->setGiftCard($this->createPendingGiftCard($code));

        /** @var PaymentFactoryInterface<PaymentInterface> $paymentFactory */
        $paymentFactory = self::getContainer()->get('sylius.factory.payment');
        $payment = $paymentFactory->createWithAmountAndCurrencyCode(5000, 'USD');
        self::assertInstanceOf(PaymentInterface::class, $payment);
        $payment->setMethod($this->createCashPaymentMethod());
        $order->addPayment($payment);

        $this->manager->persist($order);
        $this->manager->flush();

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->manager->flush();

        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState(), 'precondition: the order awaits its payment');
        self::assertFalse($unit->getGiftCard()?->isEnabled() ?? true, 'precondition: the card is not enabled before the order is paid');

        $orderId = $order->getId();
        $paymentId = $order->getLastPayment()?->getId();
        self::assertIsInt($orderId);
        self::assertIsInt($paymentId);

        return [$orderId, $paymentId];
    }

    private function findGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');

        $giftCard = $repository->findOneByCode($code);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        return $giftCard;
    }

    private function createPendingGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->disable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    private function createCashPaymentMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment_method');

        $paymentMethod = $factory->createWithGateway('offline');
        $paymentMethod->setCode('cash');
        $paymentMethod->setEnabled(true);
        $paymentMethod->getGatewayConfig()?->setGatewayName('cash');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Cash');
        $paymentMethod->addChannel($this->getChannel());

        $this->manager->persist($paymentMethod);

        return $paymentMethod;
    }
}
