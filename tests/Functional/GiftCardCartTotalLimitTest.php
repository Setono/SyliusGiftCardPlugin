<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sylius keeps an order's totals in integer columns, which hold 2147483647 minor units (sylius_core.max_int_value).
 * Gift cards that each fit could take a cart past that together, and the database refused the cart with a 500. These
 * put gift cards in a cart and raise their quantity the way a visitor does, through the product page's add to cart
 * form and the cart page's form, each posted with the token its page renders.
 *
 * Nothing is configured as the most a card may be bought for (purchase.maximum_amount), the default.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class GiftCardCartTotalLimitTest extends AdminFunctionalTestCase
{
    private const SHOP = 'http://shop.example.test';

    private const MESSAGE = 'A cart cannot total more than $21,474,836.47, so this gift card does not fit in yours.';

    private const QUANTITY_MESSAGE = 'A cart cannot total more than $21,474,836.47, so this many gift cards do not fit in yours.';

    private int $cartId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname('shop.example.test');

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
     * The first card holds the most the cart's totals can, so a card of 1.00 more used to end in a 500 on
     * sylius_order.items_total
     *
     * @test
     */
    public function it_refuses_a_gift_card_the_cart_has_no_room_left_for(): void
    {
        $product = $this->createGiftCardProduct();
        $this->assertAdded($this->addToCart($product, '21474836.47'));

        $response = $this->addToCart($product, '1.00');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([self::MESSAGE], self::formErrors($response));
        self::assertSame([2147483647], $this->giftCardAmounts(), 'the refused card should not have been made');
        self::assertSame(2147483647, $this->cart()->getItemsTotal());
    }

    /**
     * Each card fits a gift card's balance, but two of them do not fit the cart
     *
     * @test
     */
    public function it_refuses_gift_cards_that_do_not_fit_the_cart_at_the_quantity_chosen(): void
    {
        $response = $this->addToCart($this->createGiftCardProduct(), '20000000.00', 2);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([self::MESSAGE], self::formErrors($response));
        self::assertSame([], $this->giftCardAmounts());
        self::assertCount(0, $this->cart()->getItems());
    }

    /** @test */
    public function it_adds_a_gift_card_that_takes_the_cart_exactly_to_what_its_totals_hold(): void
    {
        $product = $this->createGiftCardProduct();
        $this->assertAdded($this->addToCart($product, '10737417.73', 2));

        $this->assertAdded($this->addToCart($product, '1.01'));

        self::assertSame([1073741773, 1073741773, 101], $this->giftCardAmounts());
        self::assertSame(2147483647, $this->cart()->getItemsTotal());
    }

    /**
     * Raising the quantity of a gift card line takes the cart's totals up as surely as adding the cards did, and used
     * to end in a 500 too. Sylius refuses the cart like any other invalid one: it shows the error on the line and
     * leaves the cart as it was
     *
     * @test
     */
    public function it_refuses_raising_a_gift_card_line_past_what_the_cart_holds(): void
    {
        $this->assertAdded($this->addToCart($this->createGiftCardProduct(), '20000000.00'));

        $response = $this->updateQuantity(2);

        self::assertSame(200, $response->getStatusCode(), 'Sylius renders the cart again with the errors of its form');
        self::assertSame(
            [self::QUANTITY_MESSAGE],
            self::textsOf($response, '//tr[@data-test-cart-product-row]//*[@data-test-validation-error]'),
            'the error belongs to the line whose quantity was raised',
        );

        $cart = $this->cart();
        self::assertSame(2000000000, $cart->getItemsTotal());
        self::assertSame(1, $cart->getTotalQuantity());
        self::assertSame([2000000000], $this->giftCardAmounts());
    }

    /** @test */
    public function it_raises_a_gift_card_line_to_what_the_cart_holds(): void
    {
        $this->assertAdded($this->addToCart($this->createGiftCardProduct(), '10737418.23'));

        $response = $this->updateQuantity(2);

        self::assertTrue($response->isRedirect(), sprintf('Expected the cart to be saved, got a %d response', $response->getStatusCode()));
        self::assertSame(2147483646, $this->cart()->getItemsTotal());
    }

    /**
     * Opens the product page, which renders the form's token, and posts its add to cart form
     */
    private function addToCart(Product $product, string $amount, int $quantity = 1): Response
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
     * Opens the cart page, which renders the form's token, and saves the cart with its only line at the quantity
     */
    private function updateQuantity(int $quantity): Response
    {
        $page = $this->request('GET', self::SHOP . '/en_US/cart/');
        self::assertSame(200, $page->getStatusCode(), 'the cart page should render');

        return $this->request('PATCH', self::SHOP . '/en_US/cart/', ['sylius_cart' => [
            'items' => [['quantity' => (string) $quantity]],
            '_token' => self::valueOf($page, '//input[@name="sylius_cart[_token]"]'),
        ]]);
    }

    /**
     * Sent without the page's script, which asks for an XMLHttpRequest, an added card takes the visitor to the cart
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
     * @return list<int> the amount of every gift card, read back from the database
     */
    private function giftCardAmounts(): array
    {
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');

        /** @var list<GiftCardInterface> $giftCards */
        $giftCards = $repository->findBy([], ['id' => 'ASC']);

        return array_map(static fn (GiftCardInterface $giftCard): int => $giftCard->getAmount(), $giftCards);
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

    private function createGiftCardProduct(): Product
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
