<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sylius refuses to add more of a tracked variant than is in stock, counting what the cart already holds of it: its
 * add to cart command carries CartItemAvailability. The plugin binds the add to cart form to a command of its own, which
 * lost the constraint, so a cart could take more of any product than was in stock (#457). These add to the cart the
 * way a visitor does, through the product page's add to cart form, posted with the token the page renders.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class AddToCartStockTest extends AdminFunctionalTestCase
{
    private const SHOP = 'http://shop.example.test';

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
     * Lines of the same variant merge, so the second add would have made a line of 6 of the 5 in stock. Sylius checks
     * the line's own quantity again after the form (InStock), which the 2 pass on their own
     *
     * @test
     */
    public function it_refuses_more_of_a_tracked_product_than_is_in_stock_counting_what_the_cart_holds(): void
    {
        $product = $this->createProduct('MUG', 'Mug', onHand: 5);
        $this->assertAdded($this->addToCart($product, 4));

        $response = $this->addToCart($product, 2);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['Mug does not have sufficient stock.'], self::formErrors($response));
        self::assertSame([4], $this->lineQuantities(), 'the line should hold what it held before');
    }

    /** @test */
    public function it_adds_what_is_left_in_stock_of_a_tracked_product(): void
    {
        $product = $this->createProduct('MUG', 'Mug', onHand: 5);
        $this->assertAdded($this->addToCart($product, 4));

        $this->assertAdded($this->addToCart($product, 1));

        self::assertSame([5], $this->lineQuantities());
    }

    /**
     * A gift card product is not tracked, so it has nothing in stock and is not limited by it. Its lines never merge,
     * as each one carries cards of its own
     *
     * @test
     */
    public function it_adds_a_gift_card_product_which_is_not_tracked(): void
    {
        $product = $this->createProduct('GIFT_CARD', 'Gift card', giftCard: true);
        $this->assertAdded($this->addToCart($product, 2, '50.00'));

        $this->assertAdded($this->addToCart($product, 1, '25.00'));

        self::assertSame([2, 1], $this->lineQuantities());
        self::assertSame([5000, 5000, 2500], $this->giftCardAmounts());
    }

    /**
     * Opens the product page, which renders the form's token, and posts its add to cart form
     */
    private function addToCart(Product $product, int $quantity, ?string $amount = null): Response
    {
        $page = $this->request('GET', sprintf('%s/en_US/products/%s', self::SHOP, (string) $product->getSlug()));
        self::assertSame(200, $page->getStatusCode(), 'the product page should render');

        $form = '//form[@name="sylius_add_to_cart"]';
        $action = self::textsOf($page, $form . '/@action');
        self::assertCount(1, $action, 'the product page should render the add to cart form');

        $data = [
            'cartItem' => ['quantity' => (string) $quantity],
            '_token' => self::valueOf($page, $form . '//input[@name="sylius_add_to_cart[_token]"]'),
        ];
        if (null !== $amount) {
            $data['giftCardInformation'] = ['amount' => $amount];
        }

        return $this->request('POST', self::SHOP . $action[0], ['sylius_add_to_cart' => $data]);
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
     * @return list<int> the quantity of every line of the visitor's cart, read back from the database
     */
    private function lineQuantities(): array
    {
        $this->manager->clear();

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);

        return array_values(array_map(
            static fn (OrderItemInterface $item): int => $item->getQuantity(),
            $cart->getItems()->toArray(),
        ));
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
     * A product with one variant, so the shop shows no variant choice for it. Its variant is tracked when it is given
     * a stock, the way the admin's inventory tab sets it
     */
    private function createProduct(string $code, string $name, bool $giftCard = false, ?int $onHand = null): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode($code);
        $product->setName($name);
        $product->setSlug(strtolower($code));
        $product->setGiftCard($giftCard);
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode($code . '_VARIANT');
        $variant->setName($name);
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
