<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardOrderItemsAvailability;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sylius checks the stock of a variant against one line of the cart, as lines of the same variant merge. Gift card
 * lines never merge, each carrying cards of its own, so a gift card product whose variant is tracked (printed cards
 * of which there is a limited stock, say) was only ever checked line by line: a cart could take more cards than were
 * in stock, the checkout placed the order, and paying it failed in Sylius' inventory operator (#462). These go the
 * way a visitor does, through the product page's add to cart form, the cart page's form and the checkout's complete
 * page, each posted with the token its page renders.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class GiftCardStockAcrossLinesTest extends AdminFunctionalTestCase
{
    private const SHOP = 'http://shop.example.test';

    private const MESSAGE = 'Gift card does not have sufficient stock.';

    private int $cartId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname('shop.example.test');
        // the payment method the customer pays the order with, which Sylius gives the cart when it processes it
        $this->createCashPaymentMethod();

        $cart = new Order();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $this->manager->persist($cart);
        $this->manager->flush();

        $this->cartId = (int) $cart->getId();
        $this->startSession([sprintf('_sylius.cart.%s', (string) $this->getChannel()->getCode()) => $this->cartId]);
    }

    /**
     * The 2 cards are in stock on their own, which is all Sylius looks at, as they get a line of their own
     *
     * @test
     */
    public function it_refuses_more_gift_cards_than_are_in_stock_counting_every_line_of_the_variant(): void
    {
        $product = $this->createGiftCardProduct(onHand: 5);
        $this->assertAdded($this->addToCart($product, 4));

        $response = $this->addToCart($product, 2);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([self::MESSAGE], self::formErrors($response));
        self::assertSame([4], $this->lineQuantities(), 'the cart should hold what it held before');
        self::assertCount(4, $this->giftCards(), 'the refused cards should not have been made');
    }

    /** @test */
    public function it_adds_gift_cards_up_to_what_is_in_stock_across_lines(): void
    {
        $product = $this->createGiftCardProduct(onHand: 5);
        $this->assertAdded($this->addToCart($product, 4));

        $this->assertAdded($this->addToCart($product, 1, '25.00'));

        self::assertSame([4, 1], $this->lineQuantities());
    }

    /**
     * Sylius refuses the cart like any other invalid one: it shows the error on the cart and leaves the cart as it was
     *
     * @test
     */
    public function it_refuses_raising_a_gift_card_line_past_the_stock_the_other_lines_leave(): void
    {
        $product = $this->createGiftCardProduct(onHand: 5);
        $this->assertAdded($this->addToCart($product, 4));
        $this->assertAdded($this->addToCart($product, 1));

        $response = $this->saveCart([4, 2]);

        self::assertSame(200, $response->getStatusCode(), 'Sylius renders the cart again with the errors of its form');
        self::assertSame([self::MESSAGE], self::textsOf($response, '//form[@name="sylius_cart"]/*[@data-test-validation-error]'));
        self::assertSame([], self::textsOf($response, '//tr[@data-test-cart-product-row]//*[@data-test-validation-error]'), 'no line is out of stock on its own');
        self::assertSame([4, 1], $this->lineQuantities());
    }

    /** @test */
    public function it_raises_a_gift_card_line_up_to_the_stock_the_other_lines_leave(): void
    {
        $product = $this->createGiftCardProduct(onHand: 6);
        $this->assertAdded($this->addToCart($product, 4));
        $this->assertAdded($this->addToCart($product, 1));

        $response = $this->saveCart([4, 2]);

        self::assertTrue($response->isRedirect(), sprintf('Expected the cart to be saved, got a %d response', $response->getStatusCode()));
        self::assertSame([4, 2], $this->lineQuantities());
    }

    /**
     * The stock went down after the cards were added, as another order took some or an admin corrected it. Placing
     * the order used to hold 6 of the 5 in stock, and paying it then failed in Sylius' inventory operator with
     * NotEnoughUnitsOnHandException
     *
     * @test
     */
    public function it_refuses_to_place_an_order_holding_more_gift_cards_than_are_in_stock(): void
    {
        $product = $this->createGiftCardProduct(onHand: 10);
        $this->assertAdded($this->addToCart($product, 4));
        $this->assertAdded($this->addToCart($product, 2));
        $this->setOnHand(5);

        $response = $this->placeOrder();

        self::assertSame(422, $response->getStatusCode(), 'Sylius renders the complete page again with the errors of its form');
        self::assertSame([self::MESSAGE], self::textsOf($response, '//form[@name="sylius_checkout_complete"]/*[@data-test-validation-error]'));

        $cart = $this->cart();
        self::assertSame(OrderInterface::STATE_CART, $cart->getState());
        self::assertSame(OrderCheckoutStates::STATE_PAYMENT_SELECTED, $cart->getCheckoutState());
        self::assertSame(0, $this->variant()->getOnHold(), 'nothing should have been held');
    }

    /**
     * What is in stock is held when the order is placed, and sold when it is paid, every line of the variant
     *
     * @test
     */
    public function it_places_and_sells_an_order_whose_gift_card_lines_are_in_stock_together(): void
    {
        $product = $this->createGiftCardProduct(onHand: 5);
        $this->assertAdded($this->addToCart($product, 3));
        $this->assertAdded($this->addToCart($product, 2));

        $this->assertPlaced($this->placeOrder());
        self::assertSame(5, $this->variant()->getOnHold());

        $this->completePayment();

        $variant = $this->variant();
        self::assertSame(0, $variant->getOnHand());
        self::assertSame(0, $variant->getOnHold());
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->cart()->getPaymentState());
    }

    /**
     * A gift card product is not tracked unless an admin tracks it, and the Create gift card product button builds it
     * that way. Nothing is in stock, and it is not limited by that
     *
     * @test
     */
    public function it_sells_any_number_of_gift_cards_of_a_product_that_is_not_tracked(): void
    {
        $product = $this->createGiftCardProduct();
        $this->assertAdded($this->addToCart($product, 4));
        $this->assertAdded($this->addToCart($product, 2));

        $saved = $this->saveCart([4, 20]);
        self::assertTrue($saved->isRedirect(), sprintf('Expected the cart to be saved, got a %d response', $saved->getStatusCode()));

        $this->assertPlaced($this->placeOrder());
        $this->completePayment();

        self::assertSame([4, 20], $this->lineQuantities());
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->cart()->getPaymentState());
    }

    /**
     * Sylius checks a line's stock in its own group, which the cart page and the checkout's steps validate the order
     * in, and in sylius_checkout_complete, which the complete page adds. The order's gift card lines are checked
     * together in either group, and once when both are validated, as the complete page does
     *
     * @test
     *
     * @dataProvider stockGroups
     *
     * @param list<string> $groups
     */
    public function it_checks_the_gift_card_lines_together_in_every_group_sylius_checks_stock_in(array $groups): void
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setName('Gift card');
        $product->setGiftCard(true);

        $variant = new ProductVariant();
        $variant->setTracked(true);
        $variant->setOnHand(5);
        $product->addVariant($variant);

        $order = new Order();
        foreach ([4, 2] as $quantity) {
            $item = new OrderItem();
            $item->setVariant($variant);
            for ($i = 0; $i < $quantity; ++$i) {
                new OrderItemUnit($item);
            }
            $order->addItem($item);
        }

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        $messages = [];
        foreach ($validator->validate($order, null, $groups) as $violation) {
            if ($violation->getConstraint() instanceof GiftCardOrderItemsAvailability) {
                $messages[] = [$violation->getPropertyPath(), (string) $violation->getMessage()];
            }
        }

        self::assertSame([['', self::MESSAGE]], $messages);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function stockGroups(): iterable
    {
        yield 'the cart page and the checkout\'s steps' => [['sylius']];
        yield 'placing the order' => [['sylius_checkout_complete']];
        yield 'the complete page' => [['sylius', 'sylius_checkout_complete']];
    }

    /**
     * Opens the product page, which renders the form's token, and posts its add to cart form
     */
    private function addToCart(Product $product, int $quantity, string $amount = '50.00'): Response
    {
        $page = $this->request('GET', sprintf('%s/en_US/products/%s', self::SHOP, (string) $product->getSlug()));
        self::assertSame(200, $page->getStatusCode(), 'the product page should render');

        $form = '//form[@name="sylius_add_to_cart"]';
        $action = self::textsOf($page, $form . '/@action');
        self::assertCount(1, $action, 'the product page should render the add to cart form');

        return $this->request('POST', self::SHOP . $action[0], ['sylius_add_to_cart' => [
            'cartItem' => ['quantity' => (string) $quantity],
            'giftCardInformation' => ['amount' => $amount],
            '_token' => self::valueOf($page, $form . '//input[@name="sylius_add_to_cart[_token]"]'),
        ]]);
    }

    /**
     * Sent without the page's script, which asks for an XMLHttpRequest, an added line takes the visitor to the cart
     */
    private function assertAdded(Response $response): void
    {
        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $response->getStatusCode()));
    }

    /**
     * @return list<string> the errors Sylius reports on the add to cart form itself, rather than on one of its fields
     */
    private static function formErrors(Response $response): array
    {
        /** @var array{errors?: array{form?: array{errors?: array{errors?: list<string>}}}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['errors']['form']['errors']['errors'] ?? [];
    }

    /**
     * Opens the cart page, which renders the form's token, and saves the cart with its lines at the given quantities,
     * in the order the page lists them
     *
     * @param list<int> $quantities
     */
    private function saveCart(array $quantities): Response
    {
        $page = $this->request('GET', self::SHOP . '/en_US/cart/');
        self::assertSame(200, $page->getStatusCode(), 'the cart page should render');

        $names = self::textsOf($page, '//input[@data-test-cart-item-quantity-input]/@name');
        self::assertCount(count($quantities), $names, 'the cart page should list a quantity for every line');

        $items = [];
        foreach ($names as $i => $name) {
            self::assertSame(1, preg_match('/^sylius_cart\[items]\[(\d+)]\[quantity]$/', $name, $matches), sprintf('Unexpected quantity field %s', $name));
            $items[(int) $matches[1]] = ['quantity' => (string) $quantities[$i]];
        }

        return $this->request('PATCH', self::SHOP . '/en_US/cart/', ['sylius_cart' => [
            'items' => $items,
            '_token' => self::valueOf($page, '//input[@name="sylius_cart[_token]"]'),
        ]]);
    }

    /**
     * Brings the cart to where the customer places the order, as the checkout's steps before leave it: an address,
     * no shipping as gift cards of this product are not shipped, and the payment method chosen. Then opens the
     * complete page, which renders the form's token, and posts it
     */
    private function placeOrder(): Response
    {
        $cart = $this->cart();

        $customer = new Customer();
        $customer->setEmail('buyer@example.com');
        $cart->setCustomer($customer);

        $cart->setBillingAddress(self::address());
        $cart->setShippingAddress(self::address());

        $payment = $cart->getLastPayment(PaymentInterface::STATE_CART);
        self::assertInstanceOf(PaymentInterface::class, $payment, 'processing the cart should have given it a payment');
        self::assertSame('cash', $payment->getMethod()?->getCode());

        $cart->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);
        $this->manager->flush();

        $page = $this->request('GET', self::SHOP . '/en_US/checkout/complete');
        self::assertSame(200, $page->getStatusCode(), 'the complete page should render');

        return $this->request('PUT', self::SHOP . '/en_US/checkout/complete', ['sylius_checkout_complete' => [
            'notes' => '',
            '_token' => self::valueOf($page, '//input[@name="sylius_checkout_complete[_token]"]'),
        ]]);
    }

    private static function address(): Address
    {
        $address = new Address();
        $address->setFirstName('Jane');
        $address->setLastName('Doe');
        $address->setStreet('Main street 1');
        $address->setCity('Springfield');
        $address->setPostcode('12345');
        $address->setCountryCode('US');

        return $address;
    }

    /**
     * A placed order takes the customer on to pay it
     */
    private function assertPlaced(Response $response): void
    {
        self::assertTrue($response->isRedirect(), sprintf('Expected the order to be placed, got a %d response', $response->getStatusCode()));
        self::assertStringEndsWith('/pay', (string) $response->headers->get('Location'));
        self::assertSame(OrderInterface::STATE_NEW, $this->cart()->getState());
    }

    /**
     * Completes the order's payment, as the admin's Complete button or a gateway's capture does, and flushes after
     * it the way they do
     */
    private function completePayment(): void
    {
        $payment = $this->cart()->getLastPayment(PaymentInterface::STATE_NEW);
        self::assertInstanceOf(PaymentInterface::class, $payment);

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $this->manager->flush();
    }

    /**
     * @return list<int> the quantity of every line of the visitor's cart, read back from the database
     */
    private function lineQuantities(): array
    {
        return array_values(array_map(
            static fn (OrderItemInterface $item): int => $item->getQuantity(),
            $this->cart()->getItems()->toArray(),
        ));
    }

    /**
     * @return list<GiftCardInterface> every gift card, read back from the database
     */
    private function giftCards(): array
    {
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');

        /** @var list<GiftCardInterface> $giftCards */
        $giftCards = $repository->findBy([], ['id' => 'ASC']);

        return $giftCards;
    }

    /**
     * The visitor's cart, read back from the database
     */
    private function cart(): Order
    {
        $this->manager->clear();

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);

        return $cart;
    }

    /**
     * The gift card product's variant, read back from the database
     */
    private function variant(): ProductVariant
    {
        $this->manager->clear();

        $variant = $this->manager->getRepository(ProductVariant::class)->findOneBy(['code' => 'GIFT_CARD_VARIANT']);
        self::assertInstanceOf(ProductVariant::class, $variant);

        return $variant;
    }

    private function setOnHand(int $onHand): void
    {
        $this->variant()->setOnHand($onHand);
        $this->manager->flush();
    }

    /**
     * A gift card product with one variant, so the shop shows no variant choice for it, and its cards are not
     * shipped. Its variant is tracked when it is given a stock, the way the admin's inventory tab sets it
     */
    private function createGiftCardProduct(?int $onHand = null): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD');
        $product->setName('Gift card');
        $product->setSlug('gift-card');
        $product->setGiftCard(true);
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_VARIANT');
        $variant->setName('Gift card');
        $variant->setShippingRequired(false);
        if (null !== $onHand) {
            $variant->setTracked(true);
            $variant->setOnHand($onHand);
        }

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $channel->getCode());
        $channelPricing->setPrice(5000);
        $variant->addChannelPricing($channelPricing);

        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }
}
