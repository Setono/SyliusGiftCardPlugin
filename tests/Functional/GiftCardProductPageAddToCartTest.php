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
 * Posts the add to cart form of a gift card product page through the kernel, as an anonymous visitor: with the form's
 * CSRF token, to the route the form names, which answers a refused submission with 400 and the errors of every field
 * as JSON.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class GiftCardProductPageAddToCartTest extends AdminFunctionalTestCase
{
    private const SHOP = 'http://shop.example.test';

    private const FORM = 'sylius_add_to_cart';

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
     * Nothing is configured as the maximum (purchase.maximum_amount), so an amount used to be held to nothing but the
     * minimum: more than a gift card's balance column holds, a signed 32-bit integer, ended in a 500 when the database
     * refused the cart. The error is the field's, like a blank amount's
     *
     * @test
     */
    public function it_refuses_an_amount_more_than_a_gift_card_can_hold(): void
    {
        $product = $this->createGiftCardProduct();

        $response = $this->addToCart($product, '');
        self::assertSame(400, $response->getStatusCode(), 'precondition: a blank amount is refused this way');
        self::assertSame(['This value should not be blank.'], self::amountErrors($response));

        foreach (['21474836.48', '30000000'] as $amount) {
            $response = $this->addToCart($product, $amount);

            self::assertSame(400, $response->getStatusCode(), $amount);
            self::assertSame(['The gift card amount is more than a gift card can hold.'], self::amountErrors($response), $amount);
        }

        self::assertSame([], $this->giftCards());
    }

    /**
     * The minor units of this amount are beyond PHP's integer range, where Sylius' money field wraps them around: it
     * used to put a gift card of $40.96 in the cart. The field cannot read it instead
     *
     * @test
     */
    public function it_refuses_an_amount_whose_minor_units_php_cannot_hold(): void
    {
        $response = $this->addToCart($this->createGiftCardProduct(), '184467440737095560');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['Please enter a valid money amount.'], self::amountErrors($response));
        self::assertSame([], $this->giftCards());
    }

    /** @test */
    public function it_adds_a_gift_card_holding_the_most_a_card_can_hold(): void
    {
        $response = $this->addToCart($this->createGiftCardProduct(), '21474836.47');

        // Sent without the page's script, which asks for an XMLHttpRequest, the visitor is taken to the cart
        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $response->getStatusCode()));

        $giftCards = $this->giftCards();
        self::assertCount(1, $giftCards);
        self::assertSame(2147483647, $giftCards[0]->getAmount());

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);
        self::assertSame(2147483647, $cart->getItemsTotal());
    }

    /**
     * Opens the product page, which starts the visitor's session and renders the form's token, and posts its form
     */
    private function addToCart(Product $product, string $amount): Response
    {
        $page = $this->request('GET', sprintf('%s/en_US/products/%s', self::SHOP, (string) $product->getSlug()));
        self::assertSame(200, $page->getStatusCode(), 'the product page should render');

        $form = sprintf('//form[@name="%s"]', self::FORM);
        $action = self::textsOf($page, $form . '/@action');
        self::assertCount(1, $action, 'the product page should render the add to cart form');

        return $this->request('POST', self::SHOP . $action[0], [self::FORM => [
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => $amount],
            '_token' => self::valueOf($page, sprintf('%s//input[@name="%s[_token]"]', $form, self::FORM)),
        ]]);
    }

    /**
     * @return list<string> the errors Sylius reports on the amount field
     */
    private static function amountErrors(Response $response): array
    {
        /** @var array{errors?: array{form?: array{errors?: array{children?: array{giftCardInformation?: array{children?: array{amount?: array{errors?: list<string>}}}}}}}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['errors']['form']['errors']['children']['giftCardInformation']['children']['amount']['errors'] ?? [];
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
        $giftCards = $repository->findAll();

        return $giftCards;
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
