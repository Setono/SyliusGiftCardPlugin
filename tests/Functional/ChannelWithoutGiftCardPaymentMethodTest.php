<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

/**
 * Gift card payments are made with the gift card payment method found by its code, and checkout never offers it, so
 * its channels make no difference to them (#411). The plugin creates it in no channel, and a method created before
 * that is in the channels that existed back then, not in one opened since. This takes a gift card through a channel the
 * method is not in, for each of those, the way a customer and an administrator do, through the kernel: the card applied
 * with the form on the shop's cart page, the order placed with the button on Sylius' own checkout complete step (its
 * validation and the shop's payment pages included), then the order shown, paid, refunded and cancelled with Sylius'
 * own buttons in the admin. Every figure is read back from the database
 */
final class ChannelWithoutGiftCardPaymentMethodTest extends AdminFunctionalTestCase
{
    /** the channel the shop has when the method is set up */
    private const EXISTING = 'TEST_CHANNEL';

    /** a channel opened after the method was set up */
    private const LATER = 'LATER';

    private const HOSTNAMES = [self::EXISTING => 'shop.example.test', self::LATER => 'later.example.test'];

    private const ORDER_TOTAL = 5000;

    private PaymentMethodInterface $giftCardPaymentMethod;

    /** the channel the order is placed in */
    private ChannelInterface $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname(self::HOSTNAMES[self::EXISTING]);

        // the method is set up while the test channel is the only one, and the other channel is opened afterwards
        $this->giftCardPaymentMethod = $this->createGiftCardPaymentMethod();
        $this->createChannel(self::LATER, self::HOSTNAMES[self::LATER]);

        // what the customer pays the rest with, set up in the new channel too, as a merchant opening a channel would
        $this->createCashPaymentMethod()->addChannel($this->channelOf(self::LATER));

        // the country of the customer's address, which the shop's address forms look up
        $country = new Country();
        $country->setCode('US');
        $this->manager->persist($country);

        $this->manager->flush();

        self::assertCount(0, $this->giftCardPaymentMethod->getChannels(), 'precondition: the plugin creates the method in no channel');
    }

    /**
     * @return iterable<string, array{string, list<string>}> the channel the order is placed in, and the channels the
     *                                                       gift card payment method is in
     */
    public static function channels(): iterable
    {
        yield 'a channel opened after the method was created in no channel' => [self::LATER, []];
        yield 'a channel the method is not in, while an administrator has put it in another' => [self::LATER, [self::EXISTING]];
    }

    /**
     * @test
     *
     * @dataProvider channels
     *
     * @param list<string> $methodChannels
     */
    public function a_gift_card_pays_the_whole_order(string $channel, array $methodChannels): void
    {
        $this->placeOrdersIn($channel, $methodChannels);
        $giftCard = $this->createGiftCard('NOMETHODFULL0001', 8000);

        $orderId = $this->placeOrderInTheShop($giftCard, OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        $order = $this->findOrder($orderId);
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame([['gift_card', PaymentInterface::STATE_COMPLETED, 5000]], self::paymentsOf($order));
        self::assertSame(3000, $this->persistedBalanceOf($giftCard));
        self::assertSame([[GiftCardTransactionInterface::TYPE_REDEEM, -5000]], $this->persistedLedgerOf($giftCard));

        $this->logInAsAdministrator();

        self::assertSame(['Completed Gift card $50.00 Refund'], $this->paymentsOnTheAdminOrderPage($orderId));
    }

    /**
     * @test
     *
     * @dataProvider channels
     *
     * @param list<string> $methodChannels
     */
    public function refunding_the_gift_card_payment_in_the_admin_gives_the_card_its_balance_back(string $channel, array $methodChannels): void
    {
        $this->placeOrdersIn($channel, $methodChannels);
        $giftCard = $this->createGiftCard('NOMETHODREFUND01', 8000);
        $orderId = $this->placeOrderInTheShop($giftCard, OrderCheckoutStates::STATE_PAYMENT_SKIPPED);
        $this->logInAsAdministrator();

        $paymentId = self::paymentIdOf($this->findOrder($orderId), 'gift_card');
        $this->press($orderId, sprintf('/admin/orders/%d/payments/%d/refund', $orderId, $paymentId));

        $order = $this->findOrder($orderId);
        self::assertSame(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertSame([['gift_card', PaymentInterface::STATE_REFUNDED, 5000]], self::paymentsOf($order));
        self::assertSame(8000, $this->persistedBalanceOf($giftCard), 'refunding the payment should have given the card its balance back');
        self::assertSame(
            [[GiftCardTransactionInterface::TYPE_REDEEM, -5000], [GiftCardTransactionInterface::TYPE_RESTORE, 5000]],
            $this->persistedLedgerOf($giftCard),
        );
    }

    /**
     * @test
     *
     * @dataProvider channels
     *
     * @param list<string> $methodChannels
     */
    public function a_gift_card_pays_part_of_the_order_and_the_customer_pays_the_rest(string $channel, array $methodChannels): void
    {
        $this->placeOrdersIn($channel, $methodChannels);
        $giftCard = $this->createGiftCard('NOMETHODPART0001', 3000);

        $orderId = $this->placeOrderInTheShop($giftCard, OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        $order = $this->findOrder($orderId);
        self::assertSame(OrderCheckoutStates::STATE_COMPLETED, $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState());
        self::assertSame(
            [['cash', PaymentInterface::STATE_NEW, 2000], ['gift_card', PaymentInterface::STATE_COMPLETED, 3000]],
            self::paymentsOf($order),
        );
        self::assertSame(0, $this->persistedBalanceOf($giftCard));

        // the shop's page for paying the rest, which lists the order's payments
        $orderPage = $this->request('GET', sprintf('http://%s/en_US/order/%s', $this->hostname(), (string) $order->getTokenValue()));
        self::assertSame(200, $orderPage->getStatusCode(), 'the shop page for paying the rest should render');

        $this->logInAsAdministrator();

        self::assertSame(['New Cash $20.00 Complete', 'Completed Gift card $30.00 Refund'], $this->paymentsOnTheAdminOrderPage($orderId));

        $this->press($orderId, sprintf('/admin/orders/%d/payments/%d/complete', $orderId, self::paymentIdOf($order, 'cash')));

        self::assertSame(OrderPaymentStates::STATE_PAID, $this->findOrder($orderId)->getPaymentState(), 'paying the rest should have paid the order');
    }

    /**
     * @test
     *
     * @dataProvider channels
     *
     * @param list<string> $methodChannels
     */
    public function cancelling_the_order_in_the_admin_gives_the_card_its_balance_back(string $channel, array $methodChannels): void
    {
        $this->placeOrdersIn($channel, $methodChannels);
        $giftCard = $this->createGiftCard('NOMETHODCANCEL01', 3000);
        $orderId = $this->placeOrderInTheShop($giftCard, OrderCheckoutStates::STATE_PAYMENT_SELECTED);
        $this->logInAsAdministrator();

        $page = $this->request('GET', sprintf('/admin/orders/%d', $orderId));
        self::assertSame(200, $page->getStatusCode());
        $cancel = self::textsOf($page, '//form[contains(@action, "/cancel")]/@action');
        self::assertCount(1, $cancel, 'the order page should offer to cancel the order');
        $response = $this->request('POST', $cancel[0], ['_method' => 'PUT']);
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));

        $order = $this->findOrder($orderId);
        self::assertSame(BaseOrderInterface::STATE_CANCELLED, $order->getState());
        self::assertSame(
            [['cash', PaymentInterface::STATE_CANCELLED, 2000], ['gift_card', PaymentInterface::STATE_REFUNDED, 3000]],
            self::paymentsOf($order),
        );
        self::assertSame(3000, $this->persistedBalanceOf($giftCard), 'cancelling the order should have given the card its balance back');
        self::assertSame(
            [[GiftCardTransactionInterface::TYPE_REDEEM, -3000], [GiftCardTransactionInterface::TYPE_RESTORE, 3000]],
            $this->persistedLedgerOf($giftCard),
        );
    }

    /**
     * The warning only asks whether the method exists, so channels selling gift cards that the method is not in do not
     * set it off
     *
     * @test
     */
    public function the_admin_does_not_warn_about_channels_the_payment_method_is_not_in(): void
    {
        /** @var GiftCardProductFactoryInterface $productFactory */
        $productFactory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $this->manager->persist($productFactory->create('gift_card', 'Gift card', channels: [$this->channelOf(self::EXISTING), $this->channelOf(self::LATER)]));
        $this->manager->flush();

        $this->logInAsAdministrator();

        foreach (['/admin/', '/admin/gift-cards/', '/admin/payment-methods/'] as $path) {
            $response = $this->request('GET', $path);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertSame([], self::textsOf($response, '//*[@data-test-gift-card-payment-method-warning]'), $path);
            self::assertSame([], self::textsOf($response, '//*[@data-test-gift-card-payment-method-warning-message]'), $path);
        }
    }

    /**
     * Puts the gift card payment method in the given channels and makes the order's channel the given one, which the
     * method is not in
     *
     * @param list<string> $methodChannels
     */
    private function placeOrdersIn(string $channel, array $methodChannels): void
    {
        foreach ($methodChannels as $methodChannel) {
            $this->giftCardPaymentMethod->addChannel($this->channelOf($methodChannel));
        }
        $this->manager->flush();

        $this->channel = $this->channelOf($channel);

        self::assertFalse($this->giftCardPaymentMethod->hasChannel($this->channel), 'precondition: the method is not in the channel the order is placed in');
    }

    private function channelOf(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = self::getContainer()->get('sylius.repository.channel');

        $channel = $channelRepository->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private function hostname(): string
    {
        return self::HOSTNAMES[(string) $this->channel->getCode()];
    }

    /**
     * Puts a cart in the order's channel through the shop: the customer applies the gift card with the form on the cart
     * page, and places the order with the button on the checkout's complete step, from where the shop takes them
     * through its payment pages to the thank you page. The address, shipping and payment steps are taken as done, in
     * the checkout state the payment step leaves the cart in: payment skipped where the card covers everything,
     * payment selected where the customer picked the cash method for the rest
     *
     * @return int the id of the order
     */
    private function placeOrderInTheShop(GiftCardInterface $giftCard, string $checkoutState): int
    {
        $cartId = $this->createCart();
        $hostname = $this->hostname();

        $cartPage = $this->request('GET', sprintf('http://%s/en_US/cart/', $hostname));
        self::assertSame(200, $cartPage->getStatusCode(), 'the cart page should render');

        $applied = $this->request('POST', sprintf('http://%s/en_US/gift-cards', $hostname), [
            'setono_sylius_gift_card_add_gift_card_to_order' => [
                'giftCard' => (string) $giftCard->getCode(),
                '_token' => self::valueOf($cartPage, '//input[@name="setono_sylius_gift_card_add_gift_card_to_order[_token]"]'),
            ],
        ]);
        self::assertTrue($applied->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $applied->getStatusCode()));

        $cart = $this->findOrder($cartId);
        self::assertCount(1, $cart->getGiftCards(), 'the shop should have applied the gift card');
        $cart->setCheckoutState($checkoutState);
        $this->manager->flush();

        $completeStep = sprintf('http://%s/en_US/checkout/complete', $hostname);
        $page = $this->request('GET', $completeStep);
        self::assertSame(200, $page->getStatusCode(), 'the complete step should render');

        $response = $this->request('POST', $completeStep, [
            '_method' => 'PUT',
            'sylius_checkout_complete' => [
                'notes' => '',
                '_token' => self::valueOf($page, '//input[@name="sylius_checkout_complete[_token]"]'),
            ],
        ]);

        $uri = $completeStep;
        for ($redirects = 0; $response->isRedirect() && $redirects < 10; ++$redirects) {
            // the shop redirects within the channel's host, which a relative location leaves out
            $location = (string) $response->headers->get('Location');
            $uri = str_starts_with($location, '/') ? sprintf('http://%s%s', $hostname, $location) : $location;

            $response = $this->request('GET', $uri);
        }

        self::assertSame(sprintf('http://%s/en_US/order/thank-you', $hostname), $uri, 'placing the order should have ended on the thank you page');
        self::assertSame(200, $response->getStatusCode(), 'the thank you page should render');

        return $cartId;
    }

    /**
     * Presses a button on the order's page in the admin the way a browser does: with the CSRF token the page renders
     * for it, as the PUT the form overrides its POST with
     */
    private function press(int $orderId, string $action): void
    {
        $page = $this->request('GET', sprintf('/admin/orders/%d', $orderId));
        self::assertSame(200, $page->getStatusCode());

        $response = $this->request('POST', $action, [
            '_method' => 'PUT',
            '_csrf_token' => self::valueOf($page, sprintf('//form[@action="%s"]//input[@name="_csrf_token"]', $action)),
        ]);
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got a %d response', $response->getStatusCode()));
    }

    /**
     * @return list<string> each payment on the order's page in the admin, as its state, method and amount followed by
     *                      the button for the transition it offers
     */
    private function paymentsOnTheAdminOrderPage(int $orderId): array
    {
        $page = $this->request('GET', sprintf('/admin/orders/%d', $orderId));
        self::assertSame(200, $page->getStatusCode(), 'the order page should render');

        return self::textsOf($page, '//*[@id="sylius-payments"]/*[contains(@class, "item")]');
    }

    /**
     * @return list<array{string|null, string|null, int|null}> the order's payments as method code, state and amount, by id
     */
    private static function paymentsOf(Order $order): array
    {
        $payments = [];
        foreach ($order->getPayments() as $payment) {
            $payments[(int) $payment->getId()] = [$payment->getMethod()?->getCode(), $payment->getState(), $payment->getAmount()];
        }
        ksort($payments);

        return array_values($payments);
    }

    /**
     * @return int the id of the order's one payment made with the method of the given code
     */
    private static function paymentIdOf(Order $order, string $methodCode): int
    {
        $ids = [];
        foreach ($order->getPayments() as $payment) {
            if ($methodCode === $payment->getMethod()?->getCode()) {
                $ids[] = (int) $payment->getId();
            }
        }

        self::assertCount(1, $ids, sprintf('the order should carry exactly one %s payment', $methodCode));

        return $ids[0];
    }

    /**
     * Forgets everything the entity manager holds and loads the order again, the way the next request finds it
     */
    private function findOrder(int $orderId): Order
    {
        $this->manager->clear();

        $order = $this->manager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    private function persistedBalanceOf(GiftCardInterface $giftCard): int
    {
        return (int) $this->manager
            ->createQuery(sprintf('SELECT g.amount FROM %s g WHERE g.id = :id', $this->manager->getClassMetadata(GiftCardInterface::class)->getName()))
            ->setParameter('id', $giftCard->getId())
            ->getSingleScalarResult()
        ;
    }

    /**
     * @return list<array{string, int}> the card's ledger as type and amount, oldest row first
     */
    private function persistedLedgerOf(GiftCardInterface $giftCard): array
    {
        /** @var list<array{type: string, amount: int}> $rows */
        $rows = $this->manager
            ->createQuery(sprintf(
                'SELECT t.type, t.amount FROM %s t WHERE t.giftCard = :giftCard ORDER BY t.id',
                $this->manager->getClassMetadata(GiftCardTransactionInterface::class)->getName(),
            ))
            ->setParameter('giftCard', $giftCard->getId())
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): array => [$row['type'], $row['amount']], $rows);
    }

    /**
     * A gift card of the order's channel, which is the only channel a card pays in
     */
    private function createGiftCard(string $code, int $amount): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardFactoryInterface::class);

        $giftCard = $factory->createForChannel($this->channel);
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    /**
     * A cart in the order's channel holding one ordinary item that needs no shipping, addressed and processed the way
     * the shop processes it, so it carries the payment Sylius sizes to the whole order. The visitor's session names it
     *
     * @return int the id of the cart
     */
    private function createCart(): int
    {
        $customer = new Customer();
        $customer->setEmail('buyer@example.com');
        $this->manager->persist($customer);

        $address = new Address();
        $address->setFirstName('Gift');
        $address->setLastName('Tester');
        $address->setStreet('1 Main Street');
        $address->setCity('Springfield');
        $address->setPostcode('12345');
        $address->setCountryCode('US');

        $cart = new Order();
        $cart->setChannel($this->channel);
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $cart->setCustomer($customer);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress(clone $address);
        $cart->addItem($this->createItem());

        /** @var OrderProcessorInterface $orderProcessor */
        $orderProcessor = self::getContainer()->get('sylius.order_processing.order_processor');
        $orderProcessor->process($cart);

        $this->manager->persist($cart);
        $this->manager->flush();

        self::assertSame(self::ORDER_TOTAL, $cart->getTotal(), 'precondition: the cart costs its item');
        self::assertSame([['cash', PaymentInterface::STATE_CART, self::ORDER_TOTAL]], self::paymentsOf($cart), 'precondition: the cart is to be paid in cash');

        $cartId = (int) $cart->getId();
        $this->startSession([sprintf('_sylius.cart.%s', (string) $this->channel->getCode()) => $cartId]);

        return $cartId;
    }

    /**
     * One unit of a mug sold in the order's channel only, at the order total
     */
    private function createItem(): OrderItem
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Mug');
        $product->setSlug('mug');
        $product->addChannel($this->channel);
        $this->manager->persist($product);

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $this->channel->getCode());
        $channelPricing->setPrice(self::ORDER_TOTAL);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT');
        $variant->setName('Mug');
        $variant->setShippingRequired(false);
        $variant->addChannelPricing($channelPricing);
        $product->addVariant($variant);
        $this->manager->persist($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        new OrderItemUnit($item);

        return $item;
    }
}
