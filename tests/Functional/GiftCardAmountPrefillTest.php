<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders the add to cart form the way Sylius' OrderItemController does on the product page, before anything has been
 * added to the cart. The line has no price at that point: Sylius only prices a line once it is in the cart. So the
 * amount field used to start at 0.00, an amount the shop refuses, while the page printed the product's price next to it
 */
final class GiftCardAmountPrefillTest extends GiftCardFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->getChannel();
        $channel->setHostname(self::HOSTNAME);
        $this->manager->flush();

        // the form resolves the channel from the current request, like on the product page
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create(sprintf('http://%s/en_US/products/gift-card', self::HOSTNAME)));
    }

    /** @test */
    public function it_starts_the_amount_field_at_the_price_the_product_is_sold_at(): void
    {
        self::assertSame('50.00', $this->renderAmountField($this->createGiftCardProduct(5000)));
    }

    /**
     * A merchant may well price the gift card product at zero, since the customer chooses the amount anyway. Zero is
     * never an amount the shop sells, so the field starts out empty rather than at 0.00
     *
     * @test
     */
    public function it_starts_the_amount_field_empty_when_the_product_is_sold_for_nothing(): void
    {
        self::assertSame('', $this->renderAmountField($this->createGiftCardProduct(0)));
    }

    /**
     * Does what OrderItemController::addAction() does before it renders the add to cart form on the product page, and
     * returns what the amount field holds when the customer first sees it
     */
    private function renderAmountField(Product $product): mixed
    {
        $container = self::getContainer();

        /** @var FactoryInterface<Order> $orderFactory */
        $orderFactory = $container->get('sylius.factory.order');
        $cart = $orderFactory->createNew();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');

        /** @var CartItemFactoryInterface<OrderItem> $cartItemFactory */
        $cartItemFactory = $container->get('sylius.factory.cart_item');
        $item = $cartItemFactory->createForProduct($product);

        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = $container->get(OrderItemQuantityModifierInterface::class);
        $quantityModifier->modify($item, 1);

        /** @var AddToCartCommandFactoryInterface $commandFactory */
        $commandFactory = $container->get('sylius.factory.add_to_cart_command');
        $command = $commandFactory->createWithCartAndCartItem($cart, $item);
        self::assertInstanceOf(AddToCartCommandInterface::class, $command, 'the plugin decorates the command factory');

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');

        $view = $formFactory
            ->create(AddToCartType::class, $command, ['product' => $product, 'csrf_protection' => false])
            ->createView();

        $vars = $view->children['giftCardInformation']->children['amount']->vars;
        self::assertIsArray($vars);

        return $vars['value'];
    }

    private function createGiftCardProduct(int $channelPrice): Product
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
        $channelPricing->setPrice($channelPrice);
        $variant->addChannelPricing($channelPricing);

        $product->addVariant($variant);

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }
}
