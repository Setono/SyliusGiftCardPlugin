<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Loader\FilesystemLoader;

/**
 * The shop shows a gift card product on the plugin's own page, rendered by GiftCardProductPageSubscriber in place of
 * Sylius' product page: the live preview of the card, and next to it what the customer chooses in Sylius' own add to
 * cart form. Everything on Sylius' page that does not fit a gift card is left out.
 *
 * The pages are requested through the kernel, so this is Sylius' resource controller finding the product, the plugin's
 * subscriber answering for it, and Sylius' partial route rendering the add to cart form into the page
 */
final class GiftCardProductPageTest extends AdminFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    private const PAGE = '//*[@id="setono-gift-card-information"][@data-test-gift-card-product-page]';

    private const ADD_TO_CART_FORM = '//form[@name="sylius_add_to_cart"][@id="sylius-product-adding-to-cart"]';

    private const DELIVERY = self::ADD_TO_CART_FORM . '//fieldset[@data-test-gift-card-delivery]';

    private const VARIANT_CHOICES = '//input[@type="radio"][@name="sylius_add_to_cart[cartItem][variant]"]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname(self::HOSTNAME);
        $this->manager->flush();
    }

    /** @test */
    public function it_builds_the_page_around_the_preview_of_the_card_and_what_the_customer_chooses(): void
    {
        $this->createDesign('birthday');
        $product = $this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);
        $page = $this->productPage($product);

        self::assertCount(1, self::textsOf($page, self::PAGE), 'the plugin\'s page should be rendered');
        self::assertSame(['Gift card'], self::textsOf($page, '//h1'));
        self::assertSame(['Gift card | Test channel'], self::textsOf($page, '//title'));

        // the preview takes the first column, and the form the second
        self::assertCount(1, self::textsOf($page, self::PAGE . '/div[1]//*[@data-test-gift-card-preview]//*[@data-js-gc-card]'));
        self::assertCount(1, self::textsOf($page, self::PAGE . '/div[2]' . self::ADD_TO_CART_FORM));

        // Sylius' add to cart form, posted to Sylius' cart route with its token and sending the customer to the cart
        $actions = self::textsOf($page, self::ADD_TO_CART_FORM . '/@action');
        self::assertSame([sprintf('/en_US/ajax/cart/add?productId=%d', (int) $product->getId())], $actions);
        self::assertSame(['/en_US/cart/'], self::textsOf($page, self::ADD_TO_CART_FORM . '/@data-redirect'));
        self::assertNotSame('', self::valueOf($page, self::ADD_TO_CART_FORM . '//input[@name="sylius_add_to_cart[_token]"]'));
        self::assertCount(1, self::textsOf($page, self::ADD_TO_CART_FORM . '//*[@id="sylius-cart-validation-error"]'));

        // The delivery types are a radio group named by its legend, each choice labelled with its variant's name
        self::assertSame(['Delivery'], self::textsOf($page, self::DELIVERY . '/legend'));
        self::assertSame(
            ['Virtual — delivered by email', 'Physical — shipped to you'],
            self::textsOf($page, self::DELIVERY . '//label[.' . self::VARIANT_CHOICES . ']'),
        );
        self::assertSame(['gift_card_virtual'], self::textsOf($page, self::DELIVERY . self::VARIANT_CHOICES . '[@checked]/@value'), 'the first variant should be preselected');

        // What the customer chooses comes in the order they put the card together, the quantity last
        $html = (string) $page->getContent();
        $positions = [];
        foreach ([
            'delivery' => 'name="sylius_add_to_cart[cartItem][variant]"',
            'amount' => 'name="sylius_add_to_cart[giftCardInformation][amount]"',
            'design' => 'name="sylius_add_to_cart[giftCardInformation][design]"',
            'message' => 'name="sylius_add_to_cart[giftCardInformation][customMessage]"',
            'quantity' => 'name="sylius_add_to_cart[cartItem][quantity]"',
            'button' => 'data-test-add-to-cart-button',
        ] as $field => $marker) {
            $position = strpos($html, $marker);
            self::assertIsInt($position, sprintf('the page should render the %s field', $field));
            $positions[$field] = $position;
        }
        $ordered = $positions;
        asort($ordered);
        self::assertSame(array_keys($positions), array_keys($ordered));

        // The quantity stays, and says what buying more than one gets the customer
        self::assertSame(
            ['Each gift card you buy gets a code of its own.'],
            self::textsOf($page, '//*[@id="sylius_add_to_cart_cartItem_quantity_help"]'),
        );
    }

    /**
     * The placeholder image, the reviews, the price, the code, the variant table with its prices, the empty details tab
     * and the latest products are what Sylius' page shows, none of which fits a gift card
     *
     * @test
     */
    public function it_leaves_out_what_does_not_fit_a_gift_card(): void
    {
        $page = $this->productPage($this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]));

        foreach ([
            'main image' => '//*[@data-test-main-image]',
            'reviews' => '//*[@data-test-product-reviews]',
            'average rating' => '//*[@data-test-average-rating]',
            'price' => '//*[@id="product-price"]',
            'price and code' => '//*[@data-test-product-price-content]',
            'variant table' => '//*[@id="sylius-product-variants"]',
            'tabs' => '//*[@data-test-tab]',
            'latest products' => '//*[@data-test-product]',
            'description' => '//*[@data-test-gift-card-product-description]',
        ] as $part => $expression) {
            self::assertCount(0, self::textsOf($page, $expression), sprintf('the page should not show the %s', $part));
        }

        self::assertCount(0, self::textsOf($page, '//body//*[normalize-space(text()) = "gift_card"]'), 'the page should not show the product code');
        self::assertCount(0, self::textsOf($page, '//body//*[contains(text(), "No description")]'));
        // the amount field starts at the price instead
        self::assertCount(0, self::textsOf($page, '//body//*[contains(text(), "$50.00")]'), 'the page should not print the price');
        self::assertSame('50.00', self::valueOf($page, '//input[@name="sylius_add_to_cart[giftCardInformation][amount]"]'));
    }

    /** @test */
    public function it_shows_the_descriptions_the_merchant_wrote(): void
    {
        $product = $this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual]);
        $product->setCurrentLocale('en_US');
        $product->setShortDescription('The gift of choice.');
        $product->setDescription("Spend it on anything in the shop.\nIt never expires.");
        $this->manager->flush();

        $page = $this->productPage($product);

        self::assertSame(['The gift of choice.'], self::textsOf($page, '//*[@data-test-gift-card-product-short-description]'));
        self::assertSame(['Description'], self::textsOf($page, '//*[@data-test-gift-card-product-description]/h2'));
        self::assertSame(
            ['Spend it on anything in the shop. It never expires.'],
            self::textsOf($page, '//*[@data-test-gift-card-product-description]/div'),
        );
    }

    /**
     * The customer picks the physical card on the page, and the card is issued physical: the choice is Sylius' variant
     * field, which the cart handler reads as on any product page
     *
     * @test
     */
    public function it_adds_the_card_of_the_delivery_type_the_customer_chose_to_the_cart(): void
    {
        $page = $this->productPage($this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]));

        // Sylius keeps the new cart it made for the page in a service the kernel does not reset between two requests,
        // and the reset entity manager no longer holds that cart's channel. The browser's request reaches a fresh PHP
        // process, without that cart
        $shopBasedCartContext = self::getContainer()->get('sylius.context.cart.new_shop_based');
        self::assertInstanceOf(ResetInterface::class, $shopBasedCartContext);
        $shopBasedCartContext->reset();

        $action = self::textsOf($page, self::ADD_TO_CART_FORM . '/@action');
        self::assertCount(1, $action);

        $response = $this->request('POST', sprintf('http://%s%s', self::HOSTNAME, $action[0]), [
            'sylius_add_to_cart' => [
                'cartItem' => ['quantity' => '1', 'variant' => 'gift_card_physical'],
                'giftCardInformation' => ['amount' => '25.00'],
                '_token' => self::valueOf($page, self::ADD_TO_CART_FORM . '//input[@name="sylius_add_to_cart[_token]"]'),
            ],
        ]);
        self::assertTrue($response->isRedirect('/en_US/cart/'), sprintf('the line should have been added, got a %d response: %s', $response->getStatusCode(), (string) $response->getContent()));

        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $giftCards = $repository->findAll();
        self::assertCount(1, $giftCards);

        $giftCard = reset($giftCards);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
        self::assertSame(2500, $giftCard->getAmount());
    }

    /**
     * The page is a bundle template, so an application overrides it like any other, here with one keeping Sylius' page.
     * Sylius' add to cart form gets the gift card fields from the plugin's block on Sylius' event, so the product can
     * still be bought there
     *
     * @test
     */
    public function it_renders_the_template_an_application_overrides_the_page_with(): void
    {
        $overrides = sys_get_temp_dir() . '/' . uniqid('ssgc_product_page_', true);
        mkdir($overrides . '/shop/product', 0777, true);
        file_put_contents($overrides . '/shop/product/show.html.twig', "{% extends '@SyliusShop/Product/show.html.twig' %}");

        try {
            // where templates/bundles/SetonoSyliusGiftCardPlugin of an application is looked in, ahead of the plugin's
            $loader = self::getContainer()->get('twig.loader.native_filesystem');
            self::assertInstanceOf(FilesystemLoader::class, $loader);
            $loader->prependPath($overrides, 'SetonoSyliusGiftCardPlugin');

            $page = $this->productPage($this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]));
        } finally {
            unlink($overrides . '/shop/product/show.html.twig');
            rmdir($overrides . '/shop/product');
            rmdir($overrides . '/shop');
            rmdir($overrides);
        }

        self::assertCount(0, self::textsOf($page, self::PAGE));
        // Sylius' page, with its variant table, and the gift card fields in its add to cart form
        self::assertCount(1, self::textsOf($page, '//*[@id="sylius-product-variants"]'));
        self::assertCount(1, self::textsOf($page, self::ADD_TO_CART_FORM . '//*[@id="setono-gift-card-information"]//input[@name="sylius_add_to_cart[giftCardInformation][amount]"]'));
    }

    /** @test */
    public function it_leaves_the_page_of_a_product_that_is_not_a_gift_card_to_sylius(): void
    {
        $page = $this->productPage($this->createOrdinaryProduct());

        self::assertCount(0, self::textsOf($page, self::PAGE));
        self::assertCount(0, self::textsOf($page, '//*[@id="setono-gift-card-information"]'));
        // Sylius' own page, with its price
        self::assertSame(['$12.00'], self::textsOf($page, '//*[@id="product-price"]'));
    }

    /**
     * The admin's product page runs the same controller action, and so fires the same event
     *
     * @test
     */
    public function it_leaves_the_admin_product_page_to_sylius(): void
    {
        $product = $this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);
        $this->logInAsAdministrator();

        $page = $this->request('GET', sprintf('/admin/products/%d', (int) $product->getId()));

        self::assertSame(200, $page->getStatusCode(), 'the admin product page should render');
        self::assertCount(0, self::textsOf($page, self::PAGE));
        self::assertCount(0, self::textsOf($page, '//form[@name="sylius_add_to_cart"]'));
        // Sylius' admin page, which links the product's variants
        self::assertNotSame([], self::textsOf($page, sprintf('//a[contains(@href, "/admin/products/%d/variants/")]', (int) $product->getId())));
    }

    /**
     * The shop's partial product route runs the same controller action, and renders whatever template it is asked
     * for: it is how a page shows a part of a product elsewhere, so the template asked for is what it gets
     *
     * @test
     */
    public function it_leaves_the_partial_product_route_to_sylius(): void
    {
        $product = $this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]);

        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = self::getContainer()->get('router');
        $partial = $this->request('GET', sprintf('http://%s%s', self::HOSTNAME, $urlGenerator->generate('sylius_shop_partial_product_show_by_slug', [
            '_locale' => 'en_US',
            'slug' => (string) $product->getTranslation('en_US')->getSlug(),
            'template' => '@SyliusShop/Product/Show/_header.html.twig',
        ])));

        self::assertSame(200, $partial->getStatusCode());
        self::assertSame(['Gift card'], self::textsOf($partial, '//h1[@id="sylius-product-name"]'));
        self::assertCount(0, self::textsOf($partial, '//*[@id="setono-gift-card-information"]'));
    }

    /**
     * Creates the product through the factory the admin's "create gift card product" button and the product fixture
     * use, and writes it the way they do
     *
     * @param list<GiftCardDeliveryType> $deliveryTypes
     */
    private function createGiftCardProduct(string $code, array $deliveryTypes): ProductInterface
    {
        /** @var GiftCardProductFactoryInterface $factory */
        $factory = self::getContainer()->get(GiftCardProductFactoryInterface::class);

        $product = $factory->create($code, 'Gift card', price: 5000, deliveryTypes: $deliveryTypes);
        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }

    private function createOrdinaryProduct(): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Mug');
        $product->setSlug('mug');
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT');
        $variant->setName('Mug');

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $channel->getCode());
        $channelPricing->setPrice(1200);
        $variant->addChannelPricing($channelPricing);

        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }

    private function createDesign(string $code): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName(ucfirst($code));
        $design->setEnabled(true);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }

    private function productPage(ProductInterface $product): Response
    {
        $response = $this->request('GET', sprintf(
            'http://%s/en_US/products/%s',
            self::HOSTNAME,
            (string) $product->getTranslation('en_US')->getSlug(),
        ));
        self::assertSame(200, $response->getStatusCode(), 'the product page should render');

        return $response;
    }
}
