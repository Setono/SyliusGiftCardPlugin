<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardProductFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A gift card product with a single delivery type leaves the customer nothing to choose. The factory gives it no
 * option, so Sylius treats it as a simple product: its page in the shop shows no delivery choice, and adding it to the
 * cart buys its one variant, whose shipping requirement makes the card virtual or physical.
 *
 * The pages are requested through the kernel, so this is the plugin's gift card product page with Sylius' add to cart
 * form in it, and Sylius' own controller adding the line to the cart
 */
final class SingleDeliveryTypeGiftCardProductTest extends AdminFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    private const ADD_TO_CART_FORM = '//form[@id="sylius-product-adding-to-cart"]';

    /** The choice of delivery type the page shows in place of Sylius' variant table */
    private const DELIVERY = '//fieldset[@data-test-gift-card-delivery]';

    /** The radio buttons of the delivery choice, one per variant to choose between */
    private const VARIANT_CHOICES = '//input[@name="sylius_add_to_cart[cartItem][variant]"]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname(self::HOSTNAME);
        $this->manager->flush();
    }

    /**
     * @test
     *
     * @dataProvider deliveryTypes
     */
    public function it_shows_no_variant_choice_for_a_product_with_a_single_delivery_type(GiftCardDeliveryType $deliveryType): void
    {
        $page = $this->productPage($this->createGiftCardProduct('single_card', [$deliveryType]));

        self::assertCount(0, self::textsOf($page, self::DELIVERY), 'the page should show no delivery choice');
        self::assertCount(0, self::textsOf($page, self::VARIANT_CHOICES));
        // it is still sold as a gift card, the amount chosen by the customer
        self::assertCount(1, self::textsOf($page, self::ADD_TO_CART_FORM . '//input[@name="sylius_add_to_cart[giftCardInformation][amount]"]'));
    }

    /**
     * What the test above looks for is there when there is something to choose
     *
     * @test
     */
    public function it_shows_the_variant_choice_for_a_product_with_both_delivery_types(): void
    {
        $page = $this->productPage($this->createGiftCardProduct('gift_card', [GiftCardDeliveryType::Virtual, GiftCardDeliveryType::Physical]));

        self::assertCount(1, self::textsOf($page, self::ADD_TO_CART_FORM . self::DELIVERY));
        self::assertCount(2, self::textsOf($page, self::DELIVERY . self::VARIANT_CHOICES));
    }

    /**
     * The customer submits the form on the product page without choosing a variant, as there is none to choose, and
     * the card is issued with the delivery type of the product's one variant
     *
     * @test
     *
     * @dataProvider deliveryTypes
     */
    public function it_issues_a_card_of_the_products_delivery_type_when_it_is_added_to_the_cart(GiftCardDeliveryType $deliveryType): void
    {
        $page = $this->productPage($this->createGiftCardProduct('single_card', [$deliveryType]));

        // Sylius keeps the new cart it made for the page in a service the kernel does not reset between two requests
        // (ShopBasedCartContext is not tagged kernel.reset), and the reset entity manager no longer holds that cart's
        // channel. The browser's request reaches a fresh PHP process, without that cart
        $shopBasedCartContext = self::getContainer()->get('sylius.context.cart.new_shop_based');
        self::assertInstanceOf(ResetInterface::class, $shopBasedCartContext);
        $shopBasedCartContext->reset();

        $response = $this->request('POST', sprintf('http://%s%s', self::HOSTNAME, $this->formAction($page)), [
            'sylius_add_to_cart' => [
                'cartItem' => ['quantity' => '1'],
                'giftCardInformation' => ['amount' => '25.00'],
                '_token' => self::valueOf($page, self::ADD_TO_CART_FORM . '//input[@name="sylius_add_to_cart[_token]"]'),
            ],
        ]);
        self::assertTrue($response->isRedirect(), sprintf('the line should have been added, got a %d response: %s', $response->getStatusCode(), (string) $response->getContent()));

        // what is asserted is what the request wrote, not what the entity manager still holds from it
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $giftCards = $giftCardRepository->findAll();
        self::assertCount(1, $giftCards);

        $giftCard = reset($giftCards);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);
        self::assertSame($deliveryType, $giftCard->getDeliveryType());
        self::assertSame(2500, $giftCard->getAmount());
        self::assertTrue($giftCard->isPending());

        $unit = $giftCard->getOrderItemUnit();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $item = $unit->getOrderItem();
        self::assertInstanceOf(OrderItemInterface::class, $item);
        $variant = $item->getVariant();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        self::assertSame('single_card_' . $deliveryType->value, $variant->getCode());
        // the card is physical because its variant is shipped, as a card of a product with both delivery types is
        self::assertSame(GiftCardDeliveryType::Physical === $deliveryType, $variant->isShippingRequired());
    }

    /**
     * The admin edits the product like any simple product: the shipping requirement of its one variant, which decides
     * the delivery type, is a checkbox on the product's own form
     *
     * @test
     *
     * @dataProvider deliveryTypes
     */
    public function it_lets_the_admin_edit_a_product_with_a_single_delivery_type_as_a_simple_product(GiftCardDeliveryType $deliveryType): void
    {
        $product = $this->createGiftCardProduct('single_card', [$deliveryType]);
        $this->logInAsAdministrator();

        $page = $this->request('GET', sprintf('/admin/products/%d/edit', (int) $product->getId()));
        self::assertSame(200, $page->getStatusCode(), 'the product edit page should render');

        $shippingRequired = '//input[@type="checkbox"][@name="sylius_product[variant][shippingRequired]"]';
        self::assertCount(1, self::textsOf($page, $shippingRequired));
        self::assertCount(GiftCardDeliveryType::Physical === $deliveryType ? 1 : 0, self::textsOf($page, $shippingRequired . '[@checked]'));
        self::assertCount(1, self::textsOf($page, '//input[@type="checkbox"][@name="sylius_product[giftCard]"][@checked]'));
    }

    /**
     * @return iterable<string, array{GiftCardDeliveryType}>
     */
    public static function deliveryTypes(): iterable
    {
        yield 'virtual' => [GiftCardDeliveryType::Virtual];

        yield 'physical' => [GiftCardDeliveryType::Physical];
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

        $product = $factory->create($code, 'Gift card', deliveryTypes: $deliveryTypes);
        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
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

    /**
     * Where the product page sends its add to cart form
     */
    private function formAction(Response $page): string
    {
        $actions = self::textsOf($page, self::ADD_TO_CART_FORM . '/@action');
        self::assertCount(1, $actions, 'the product page should have an add to cart form');

        return $actions[0];
    }
}
