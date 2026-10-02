<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sylius' thank you page shows the instructions of the order's last payment, such as where to send a bank transfer.
 * The gift card payments are added to an order when it is placed, after the payment the customer chose for the rest,
 * so on an order the gift cards pay in part the last payment is a gift card payment. The page has to show the
 * instructions for the rest all the same, and every other order has to get the page it always got.
 *
 * The page is requested through the kernel, so it is Sylius' own template with the block the plugin adds to it
 */
final class ThankYouPagePaymentInstructionsTest extends AdminFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    private const BANK_TRANSFER = 'Transfer the amount to IBAN DK50 0040 0440 1162 43, quoting the order number';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname(self::HOSTNAME);
        $this->manager->flush();
    }

    /** @test */
    public function it_shows_the_instructions_for_the_rest_of_an_order_a_gift_card_pays_in_part(): void
    {
        $order = $this->placeOrder(10000, $this->createEnabledGiftCard('THANKYOU00000001', 6000), 4000);
        self::assertTrue($this->isGiftCardPayment($this->lastPayment($order)), 'precondition: the gift card payment is the last one');

        self::assertSame([self::BANK_TRANSFER], $this->instructionsOn($this->thankYouPage($order)));
    }

    /** @test */
    public function it_shows_the_instructions_of_an_order_without_gift_cards_once(): void
    {
        $order = $this->placeOrder(10000, null, 10000);

        self::assertSame([self::BANK_TRANSFER], $this->instructionsOn($this->thankYouPage($order)));
    }

    /**
     * The gift cards pay everything, so the order has no other payment and there is nothing to instruct about
     *
     * @test
     */
    public function it_shows_no_instructions_for_an_order_the_gift_cards_pay_in_full(): void
    {
        $order = $this->placeOrder(10000, $this->createEnabledGiftCard('THANKYOU00000002', 15000), null);

        self::assertSame([], $this->instructionsOn($this->thankYouPage($order)));
    }

    /**
     * When the payment for the rest fails, Sylius adds a replacement after the gift card payment, and its thank you
     * page (the customer lands on it again after paying the rest) shows that payment's instructions itself
     *
     * @test
     */
    public function it_leaves_the_instructions_to_sylius_when_the_last_payment_is_the_one_for_the_rest(): void
    {
        $order = $this->placeOrder(10000, $this->createEnabledGiftCard('THANKYOU00000003', 6000), 4000);

        $this->stateMachine()->apply($this->restPayment($order), PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        $this->manager->flush();
        self::assertFalse($this->isGiftCardPayment($this->lastPayment($order)), 'precondition: the replacement is the last payment');

        self::assertSame([self::BANK_TRANSFER], $this->instructionsOn($this->thankYouPage($order)));
    }

    /**
     * The gift card payment method has no instructions unless an administrator gives it some. If they do, Sylius
     * shows them, as the last payment's, and the instructions for the rest are shown above them
     *
     * @test
     */
    public function it_shows_the_instructions_for_the_rest_above_those_of_the_gift_card_payment(): void
    {
        $order = $this->placeOrder(10000, $this->createEnabledGiftCard('THANKYOU00000004', 6000), 4000);

        $giftCardPaymentMethod = $this->lastPayment($order)->getMethod();
        self::assertInstanceOf(PaymentMethodInterface::class, $giftCardPaymentMethod);
        $giftCardPaymentMethod->setCurrentLocale('en_US');
        $giftCardPaymentMethod->setInstructions('Your gift card paid part of this order');
        $this->manager->flush();

        self::assertSame(
            [self::BANK_TRANSFER, 'Your gift card paid part of this order'],
            $this->instructionsOn($this->thankYouPage($order)),
        );
    }

    /**
     * Requests the thank you page the way the shop shows it right after the order is placed: the session names the
     * order the customer just placed
     */
    private function thankYouPage(Order $order): Response
    {
        $this->startSession(['sylius_order_id' => $order->getId()]);

        $response = $this->request('GET', sprintf('http://%s/en_US/order/thank-you', self::HOSTNAME));
        self::assertSame(200, $response->getStatusCode(), 'the thank you page should render');

        return $response;
    }

    /**
     * @return list<string> the payment instructions the page shows, in the order it shows them
     */
    private function instructionsOn(Response $response): array
    {
        return self::textsOf($response, '//*[@data-test-payment-method-instructions]');
    }

    /**
     * Places an order for an ordinary product, with the gift card applied when one is given, and with a payment of
     * the given amount for the rest by bank transfer, or none when the gift card covers everything
     */
    private function placeOrder(int $unitPrice, ?GiftCardInterface $giftCard, ?int $rest): Order
    {
        $customer = new Customer();
        $customer->setEmail('thank-you@example.com');
        $this->manager->persist($customer);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);
        $order->setCheckoutState(null === $rest ? OrderCheckoutStates::STATE_PAYMENT_SKIPPED : OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $this->addItem($order, 'MUG', $unitPrice);
        if (null !== $giftCard) {
            $order->addGiftCard($giftCard);
        }

        if (null !== $rest) {
            /** @var PaymentFactoryInterface<PaymentInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.payment');

            $payment = $factory->createWithAmountAndCurrencyCode($rest, 'USD');
            self::assertInstanceOf(PaymentInterface::class, $payment);
            $payment->setMethod($this->createBankTransferPaymentMethod());
            $order->addPayment($payment);
        }

        $this->manager->persist($order);
        $this->manager->flush();

        $this->stateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->manager->flush();

        return $order;
    }

    private function createBankTransferPaymentMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment_method');

        $paymentMethod = $factory->createWithGateway('offline');
        $paymentMethod->setCode('bank_transfer');
        $paymentMethod->setEnabled(true);
        $paymentMethod->getGatewayConfig()?->setGatewayName('bank_transfer');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Bank transfer');
        $paymentMethod->setInstructions(self::BANK_TRANSFER);
        $paymentMethod->addChannel($this->getChannel());

        $this->manager->persist($paymentMethod);

        return $paymentMethod;
    }

    private function lastPayment(Order $order): PaymentInterface
    {
        $payment = $order->getPayments()->last();
        self::assertInstanceOf(PaymentInterface::class, $payment);

        return $payment;
    }

    /**
     * The payment for what the gift card does not cover, or the replacement Sylius provided for it
     */
    private function restPayment(Order $order): PaymentInterface
    {
        $rest = null;
        foreach ($order->getPayments() as $payment) {
            self::assertInstanceOf(PaymentInterface::class, $payment);

            if (!$this->isGiftCardPayment($payment)) {
                $rest = $payment;
            }
        }
        self::assertNotNull($rest, 'the order should carry a payment for the rest');

        return $rest;
    }

    private function isGiftCardPayment(PaymentInterface $payment): bool
    {
        /** @var string $paymentMethodCode */
        $paymentMethodCode = self::getContainer()->getParameter('setono_sylius_gift_card.redemption.payment_method_code');

        return $payment->getMethod()?->getCode() === $paymentMethodCode;
    }

    private function stateMachine(): StateMachineInterface
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        return $stateMachine;
    }
}
