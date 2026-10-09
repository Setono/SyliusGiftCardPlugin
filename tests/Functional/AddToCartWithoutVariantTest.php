<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\HttpFoundation\Response;

/**
 * The add to cart form of a product with several variants asks which one to add. A browser always sends the choice,
 * as it is required, but a request without it left the line without a variant, and Sylius' stock check, which the
 * plugin maps on its add to cart command like Sylius maps it on its own, read the variant regardless: the request
 * ended in a 500 (#479). The plugin binds the form to its command for every product, so this held for any product
 * with several variants, not only for gift card products.
 *
 * These post the product page's add to cart form the way a visitor does, with the token the page renders, to the
 * route the form names, which answers a refused submission with 400 and the errors as JSON.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class AddToCartWithoutVariantTest extends AdminFunctionalTestCase
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
     * The gift card product the test application seeds, with a virtual and a physical variant, posted with a valid
     * amount. The same request with a variant adds the line, so the variant is all it lacks
     *
     * @test
     */
    public function it_refuses_a_gift_card_product_added_without_a_variant(): void
    {
        /** @var GiftCardProductFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $product = $factory->create('gift_card', 'Gift card', deliveryTypes: [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);
        $this->manager->persist($product);
        $this->manager->flush();

        $giftCardInformation = ['giftCardInformation' => ['amount' => '25.00']];

        $response = $this->addToCart($product, $giftCardInformation);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['Please choose a variant.'], self::variantErrors($response));
        self::assertSame(['Please choose a variant.'], self::allErrors($response), 'nothing else should be refused');
        self::assertSame(0, $this->lineCount());
        self::assertSame(0, $this->giftCardCount());

        $this->assertAdded($this->addToCart($product, $giftCardInformation, 'gift_card_physical'));
        self::assertSame(1, $this->lineCount());
        self::assertSame(1, $this->giftCardCount());
    }

    /** @test */
    public function it_refuses_any_other_product_added_without_a_variant(): void
    {
        $product = $this->createProductWithSizes();

        $response = $this->addToCart($product);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['Please choose a variant.'], self::variantErrors($response));
        self::assertSame(['Please choose a variant.'], self::allErrors($response), 'nothing else should be refused');
        self::assertSame(0, $this->lineCount());

        $this->assertAdded($this->addToCart($product, variantCode: 'T_SHIRT_M'));
        self::assertSame(1, $this->lineCount());
    }

    /**
     * Opens the product page, which renders the form's token and a choice of variants, and posts its add to cart form
     * with the given variant, or without one
     *
     * @param array<string, mixed> $fields the form's fields besides the line's and the token
     */
    private function addToCart(ProductInterface $product, array $fields = [], ?string $variantCode = null): Response
    {
        $page = $this->request('GET', sprintf('%s/en_US/products/%s', self::SHOP, (string) $product->getTranslation('en_US')->getSlug()));
        self::assertSame(200, $page->getStatusCode(), 'the product page should render');

        $form = sprintf('//form[@name="%s"]', self::FORM);
        $action = self::textsOf($page, $form . '/@action');
        self::assertCount(1, $action, 'the product page should render the add to cart form');
        self::assertCount(2, self::textsOf($page, sprintf('%s//input[@name="%s[cartItem][variant]"]', $form, self::FORM)), 'the page should offer a choice of the two variants');

        $cartItem = ['quantity' => '1'];
        if (null !== $variantCode) {
            $cartItem['variant'] = $variantCode;
        }

        return $this->request('POST', self::SHOP . $action[0], [self::FORM => [
            'cartItem' => $cartItem,
            '_token' => self::valueOf($page, sprintf('%s//input[@name="%s[_token]"]', $form, self::FORM)),
        ] + $fields]);
    }

    /**
     * Sent without the page's script, which asks for an XMLHttpRequest, an added line takes the visitor to the cart
     */
    private function assertAdded(Response $response): void
    {
        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('Expected a redirect to the cart, got a %d response', $response->getStatusCode()));
    }

    /**
     * @return list<string> the errors Sylius reports on the variant field
     */
    private static function variantErrors(Response $response): array
    {
        /** @var array{errors?: array{form?: array{errors?: array{children?: array{cartItem?: array{children?: array{variant?: array{errors?: list<string>}}}}}}}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['errors']['form']['errors']['children']['cartItem']['children']['variant']['errors'] ?? [];
    }

    /**
     * @return list<string> every error of the form and its fields, which Sylius' add to cart script shows the visitor
     */
    private static function allErrors(Response $response): array
    {
        /** @var array{errors?: array{errors?: list<string>}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['errors']['errors'] ?? [];
    }

    /**
     * The number of lines the visitor's cart holds, read back from the database
     */
    private function lineCount(): int
    {
        $this->manager->clear();

        $cart = $this->manager->find(Order::class, $this->cartId);
        self::assertInstanceOf(Order::class, $cart);

        return $cart->getItems()->count();
    }

    private function giftCardCount(): int
    {
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');

        return \count($repository->findAll());
    }

    /**
     * A product that is not a gift card, with a variant per size and no option, so its page offers a choice of
     * variants
     */
    private function createProductWithSizes(): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('T_SHIRT');
        $product->setName('T-shirt');
        $product->setSlug('t-shirt');
        $product->setGiftCard(false);
        $product->addChannel($channel);

        foreach (['S', 'M'] as $size) {
            $variant = new ProductVariant();
            $variant->setCurrentLocale('en_US');
            $variant->setFallbackLocale('en_US');
            $variant->setCode('T_SHIRT_' . $size);
            $variant->setName('T-shirt ' . $size);

            $channelPricing = new ChannelPricing();
            $channelPricing->setChannelCode((string) $channel->getCode());
            $channelPricing->setPrice(2000);
            $variant->addChannelPricing($channelPricing);

            $product->addVariant($variant);
        }

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }
}
