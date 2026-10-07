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
 * Sylius' add to cart routes make the line with the product's default variant, which is its first enabled one, so the
 * line of a product none of whose variants is enabled has no variant. The plugin's form type extension read the
 * product off that line while the form was built, before the request was read, and every add to cart of such a product
 * ended in a 500, gift card or not (#480).
 *
 * The product page offers no add to cart form for such a product, Sylius shows it as out of stock. So these open the
 * page while the product can still be bought, disable its variants, and then post the form the page rendered, with its
 * token, to the route it names, which answers a refused submission with 400 and the errors as JSON. That is a page
 * opened before the admin disabled the variants, or a request made by hand.
 *
 * The visitor already has a cart, which their session names. A cart that does not exist yet would be made by Sylius'
 * shop based cart context, which keeps it from one request to the next under the test kernel: Sylius tags its
 * resettable cart contexts before their decorators take over the tag, so that one is never reset. The cart would then
 * reach the second request with a channel the entity manager has let go of since
 */
final class AddToCartWithoutEnabledVariantTest extends AdminFunctionalTestCase
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
     * The form the page rendered, as a browser submits it: the gift card product the test application seeds offers a
     * variant per delivery type, and the page had one of them checked. Sylius' variant choice offers every variant of
     * the product, disabled ones too, so the line gets that variant once the form is submitted. The gift card form is
     * built from the product the add to cart route looks up rather than from the line, so the amount is asked for and
     * checked all the same
     *
     * @test
     */
    public function it_checks_the_amount_of_a_gift_card_product_without_an_enabled_variant(): void
    {
        $product = $this->createGiftCardProduct([GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);

        $response = $this->addToCartOnceDisabled($product, ['giftCardInformation' => ['amount' => '']], 'gift_card_virtual');

        self::assertSame(400, $response->getStatusCode(), self::describe($response));
        $amountErrors = self::fieldErrors($response, 'giftCardInformation', 'amount');
        self::assertCount(1, $amountErrors, 'the amount should be refused');
        self::assertSame($amountErrors, self::allErrors($response), 'nothing else should be refused');
        self::assertSame(0, $this->lineCount());
        self::assertSame(0, $this->giftCardCount());
    }

    /**
     * The request the issue gives: the quantity and the token, here with a valid amount, and no variant
     *
     * @test
     */
    public function it_refuses_a_gift_card_product_without_an_enabled_variant(): void
    {
        $product = $this->createGiftCardProduct([GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);

        $response = $this->addToCartOnceDisabled($product, ['giftCardInformation' => ['amount' => '25.00']]);

        self::assertSame(400, $response->getStatusCode(), self::describe($response));
        self::assertSame(['Please choose a variant.'], self::fieldErrors($response, 'cartItem', 'variant'));
        self::assertSame(['Please choose a variant.'], self::allErrors($response), 'nothing else should be refused');
        self::assertSame(0, $this->lineCount());
        self::assertSame(0, $this->giftCardCount());
    }

    /**
     * Without a variant as well, the amount is asked for and checked, on a product with a choice of variants and on a
     * simple one, whose form has no variant field
     *
     * @test
     *
     * @dataProvider giftCardDeliveryTypes
     *
     * @param list<GiftCardDeliveryType> $deliveryTypes
     */
    public function it_still_asks_for_the_amount_of_a_gift_card_product_without_an_enabled_variant(array $deliveryTypes): void
    {
        $product = $this->createGiftCardProduct($deliveryTypes);

        $response = $this->addToCartOnceDisabled($product, ['giftCardInformation' => ['amount' => '']]);

        self::assertSame(400, $response->getStatusCode(), self::describe($response));
        self::assertCount(1, self::fieldErrors($response, 'giftCardInformation', 'amount'), 'the amount should be refused');
        self::assertContains('Please choose a variant.', self::allErrors($response));
        self::assertSame(0, $this->lineCount());
        self::assertSame(0, $this->giftCardCount());
    }

    /**
     * @return iterable<string, array{list<GiftCardDeliveryType>}>
     */
    public static function giftCardDeliveryTypes(): iterable
    {
        yield 'a product with a variant per delivery type' => [[GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]];

        // a simple product, whose page has no variant choice
        yield 'a product with a single delivery type' => [[GiftCardDeliveryType::Virtual]];
    }

    /**
     * A simple product, whose add to cart form has no variant field: the error is the form's own
     *
     * @test
     */
    public function it_refuses_any_other_product_without_an_enabled_variant(): void
    {
        $product = $this->createProduct();

        $response = $this->addToCartOnceDisabled($product);

        self::assertSame(400, $response->getStatusCode(), self::describe($response));
        self::assertSame(['Please choose a variant.'], self::allErrors($response));
        self::assertSame(0, $this->lineCount());
    }

    /**
     * What the shop shows a visitor who opens the page now: Sylius' out of stock message instead of the add to cart
     * form, for a gift card product as for any other
     *
     * @test
     *
     * @dataProvider products
     */
    public function it_shows_a_product_without_an_enabled_variant_as_out_of_stock(string $kind): void
    {
        $product = 'gift card' === $kind ? $this->createGiftCardProduct([GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]) : $this->createProduct();
        $this->disableVariants($product);

        $page = $this->productPage($product);

        self::assertSame(200, $page->getStatusCode(), self::describe($page));
        self::assertCount(1, self::textsOf($page, '//*[@data-test-product-out-of-stock]'), 'the page should show the product as out of stock');
        self::assertCount(0, self::textsOf($page, sprintf('//form[@name="%s"]', self::FORM)), 'the page should offer no add to cart form');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function products(): iterable
    {
        yield 'a gift card product' => ['gift card'];
        yield 'any other product' => ['other'];
    }

    /**
     * Opens the product page while the product can be bought, which renders the add to cart form and its token,
     * disables every variant of the product, and then posts the form with the given variant, or without one
     *
     * @param array<string, mixed> $fields the form's fields besides the line's and the token
     */
    private function addToCartOnceDisabled(ProductInterface $product, array $fields = [], ?string $variantCode = null): Response
    {
        $page = $this->productPage($product);
        self::assertSame(200, $page->getStatusCode(), 'the product page should render');

        $form = sprintf('//form[@name="%s"]', self::FORM);
        $action = self::textsOf($page, $form . '/@action');
        self::assertCount(1, $action, 'the product page should render the add to cart form');
        $token = self::valueOf($page, sprintf('%s//input[@name="%s[_token]"]', $form, self::FORM));

        $cartItem = ['quantity' => '1'];
        if (null !== $variantCode) {
            $checked = sprintf('%s//input[@name="%s[cartItem][variant]"][@value="%s"][@checked]', $form, self::FORM, $variantCode);
            self::assertCount(1, self::textsOf($page, $checked), 'the page should have the variant checked');
            $cartItem['variant'] = $variantCode;
        }

        $this->disableVariants($product);

        return $this->request('POST', self::SHOP . $action[0], [self::FORM => [
            'cartItem' => $cartItem,
            '_token' => $token,
        ] + $fields]);
    }

    private function productPage(ProductInterface $product): Response
    {
        return $this->request('GET', sprintf('%s/en_US/products/%s', self::SHOP, (string) $product->getTranslation('en_US')->getSlug()));
    }

    /**
     * What the admin does on each of the product's variants. The kernel let go of the product with the last request,
     * so it is looked up again
     */
    private function disableVariants(ProductInterface $product): void
    {
        $product = $this->manager->find(Product::class, $product->getId());
        self::assertInstanceOf(Product::class, $product);
        self::assertNotEmpty($product->getVariants());

        foreach ($product->getVariants() as $variant) {
            $variant->setEnabled(false);
        }

        $this->manager->flush();
    }

    /**
     * @return list<string> the errors Sylius reports on one field of the add to cart form
     */
    private static function fieldErrors(Response $response, string $field, string $child): array
    {
        /** @var array{errors?: array{form?: array{errors?: array{children?: array<string, array{children?: array<string, array{errors?: list<string>}>}>}}}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['errors']['form']['errors']['children'][$field]['children'][$child]['errors'] ?? [];
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
     * What a failing assertion on the status should say: the start of the response, which names the exception of a 500
     */
    private static function describe(Response $response): string
    {
        return sprintf('Got a %d response: %s', $response->getStatusCode(), mb_substr((string) $response->getContent(), 0, 1000));
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
     * Made the way the fixtures seed the test application's gift card products
     *
     * @param list<GiftCardDeliveryType> $deliveryTypes
     */
    private function createGiftCardProduct(array $deliveryTypes): ProductInterface
    {
        /** @var GiftCardProductFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardProductFactoryInterface::class);
        $product = $factory->create('gift_card', 'Gift card', deliveryTypes: $deliveryTypes);
        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }

    /**
     * A product that is not a gift card, with one variant, so the shop shows no variant choice for it
     */
    private function createProduct(): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Mug');
        $product->setSlug('mug');
        $product->setGiftCard(false);
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT');
        $variant->setName('Mug');

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $channel->getCode());
        $channelPricing->setPrice(2000);
        $variant->addChannelPricing($channelPricing);

        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }
}
