<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds gift cards to a cart the way Sylius' OrderItemController does on the product page, through the services the
 * application wires: the cart item factory, the decorated add to cart command factory, Sylius' add to cart form
 * with the plugin's extension, the validator with the configured limits and the order modifier that processes the
 * cart afterwards. The unit tests cover each piece; this covers that they are put together
 */
final class AddToCartGiftCardTest extends GiftCardFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    /** What the variant is sold at on the channel, which a gift card line must not end up priced at */
    private const CHANNEL_PRICE = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->getChannel();
        $channel->setHostname(self::HOSTNAME);
        $this->manager->flush();

        // the form and the validator resolve the channel from the current request, like on the product page
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create(sprintf('http://%s/en_US/products/gift-card', self::HOSTNAME)));
    }

    /** @test */
    public function it_issues_a_pending_card_for_every_unit_of_the_chosen_amount(): void
    {
        $design = $this->createDesign('birthday');
        $product = $this->createProduct('GIFT_CARD', giftCard: true);

        $cart = $this->createCart();
        $form = $this->createAddToCartForm($cart, $product);
        $form->submit([
            'cartItem' => ['quantity' => '2'],
            'giftCardInformation' => [
                'amount' => '50.00',
                'customMessage' => 'Happy birthday',
                'design' => 'birthday',
            ],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $this->addToCart($form);

        $item = $this->reloadOnlyItem($cart);
        // the chosen amount, not the price the variant is sold at, survives the order processing that followed
        self::assertSame(5000, $item->getUnitPrice());
        self::assertTrue($item->isImmutable());
        self::assertSame(10000, $item->getTotal());

        $codes = [];
        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            self::assertInstanceOf(GiftCardInterface::class, $giftCard, 'every unit should carry a card of its own');
            self::assertFalse($giftCard->isEnabled(), 'a card must not be spendable before the order is paid');
            self::assertTrue($giftCard->isPending());
            self::assertSame(5000, $giftCard->getAmount());
            self::assertSame(5000, $giftCard->getInitialAmount());
            self::assertSame('USD', $giftCard->getCurrencyCode());
            self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
            self::assertSame('Happy birthday', $giftCard->getCustomMessage());
            self::assertSame($design->getId(), $giftCard->getDesign()?->getId());
            self::assertSame($this->getChannel()->getId(), $giftCard->getChannel()?->getId());
            self::assertNotNull($giftCard->getExpiresAt(), 'the configured validity period should apply');

            $codes[] = $giftCard->getCode();
        }

        self::assertCount(2, $codes);
        self::assertCount(2, array_unique($codes));
    }

    /** @test */
    public function it_issues_a_physical_card_for_a_gift_card_that_is_shipped(): void
    {
        $product = $this->createProduct('PHYSICAL_GIFT_CARD', giftCard: true, shippingRequired: true);

        $cart = $this->createCart();
        $form = $this->createAddToCartForm($cart, $product);
        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => '25.00'],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $this->addToCart($form);

        $unit = $this->reloadOnlyItem($cart)->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        $giftCard = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
        self::assertSame(2500, $giftCard->getAmount());
    }

    /**
     * The configured minimum is 1.00 (the default of setono_sylius_gift_card.purchase.minimum_amount)
     *
     * @test
     */
    public function it_issues_no_card_for_an_amount_the_shop_does_not_sell(): void
    {
        $product = $this->createProduct('GIFT_CARD', giftCard: true);

        $cart = $this->createCart();
        $form = $this->createAddToCartForm($cart, $product);
        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => '0.99'],
        ]);

        self::assertFalse($form->isValid());

        $errors = $form->get('giftCardInformation')->get('amount')->getErrors();
        self::assertCount(1, $errors);

        $item = $this->formItem($form);
        self::assertFalse($item->isImmutable(), 'the line should not have been priced at the rejected amount');

        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);
            self::assertNull($unit->getGiftCard());
        }

        self::assertSame([], $this->scheduledGiftCardInsertions());
    }

    /**
     * The configured maximum message length is 200 characters (the default of
     * setono_sylius_gift_card.purchase.maximum_message_length)
     *
     * @test
     */
    public function it_issues_no_card_for_a_message_longer_than_the_configured_limit(): void
    {
        $product = $this->createProduct('GIFT_CARD', giftCard: true);

        $cart = $this->createCart();
        $form = $this->createAddToCartForm($cart, $product);
        $form->submit([
            'cartItem' => ['quantity' => '1'],
            'giftCardInformation' => ['amount' => '50.00', 'customMessage' => str_repeat('a', 201)],
        ]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('giftCardInformation')->get('customMessage')->getErrors());
        self::assertSame([], $this->scheduledGiftCardInsertions());
    }

    /** @test */
    public function it_adds_any_other_product_the_way_sylius_does(): void
    {
        $product = $this->createProduct('MUG', giftCard: false);

        $cart = $this->createCart();
        $form = $this->createAddToCartForm($cart, $product);

        self::assertFalse($form->has('giftCardInformation'));

        $form->submit(['cartItem' => ['quantity' => '1']]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $this->addToCart($form);

        $item = $this->reloadOnlyItem($cart);
        self::assertSame(self::CHANNEL_PRICE, $item->getUnitPrice());
        self::assertFalse($item->isImmutable());

        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        self::assertNull($unit->getGiftCard());
    }

    /**
     * @return FormInterface<AddToCartCommandInterface>
     */
    private function createAddToCartForm(Order $cart, Product $product): FormInterface
    {
        $container = self::getContainer();

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

        /** @var FormInterface<AddToCartCommandInterface> $form */
        $form = $formFactory->create(AddToCartType::class, $command, [
            'product' => $product,
            // the test submits the form directly rather than through a request carrying a token
            'csrf_protection' => false,
        ]);

        return $form;
    }

    /**
     * What OrderItemController does with a valid form: add the line to the cart, which processes the cart, and flush
     *
     * @param FormInterface<AddToCartCommandInterface> $form
     */
    private function addToCart(FormInterface $form): void
    {
        $command = $form->getData();
        self::assertInstanceOf(AddToCartCommandInterface::class, $command);

        /** @var OrderModifierInterface $orderModifier */
        $orderModifier = self::getContainer()->get('sylius.order_modifier');
        $orderModifier->addToOrder($command->getCart(), $command->getCartItem());

        $this->manager->persist($command->getCart());
        $this->manager->flush();
    }

    /**
     * @param FormInterface<AddToCartCommandInterface> $form
     */
    private function formItem(FormInterface $form): OrderItem
    {
        $command = $form->getData();
        self::assertInstanceOf(AddToCartCommandInterface::class, $command);

        $item = $command->getCartItem();
        self::assertInstanceOf(OrderItem::class, $item);

        return $item;
    }

    /**
     * Reads the cart back from the database, so what is asserted is what was persisted
     */
    private function reloadOnlyItem(Order $cart): OrderItem
    {
        $id = $cart->getId();
        self::assertNotNull($id);

        $this->manager->clear();

        $reloaded = $this->manager->find(Order::class, $id);
        self::assertInstanceOf(Order::class, $reloaded);
        self::assertCount(1, $reloaded->getItems());

        $item = $reloaded->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);

        return $item;
    }

    /**
     * @return list<object>
     */
    private function scheduledGiftCardInsertions(): array
    {
        return array_values(array_filter(
            $this->manager->getUnitOfWork()->getScheduledEntityInsertions(),
            static fn (object $entity): bool => $entity instanceof GiftCardInterface,
        ));
    }

    private function createCart(): Order
    {
        /** @var FactoryInterface<Order> $orderFactory */
        $orderFactory = self::getContainer()->get('sylius.factory.order');

        $cart = $orderFactory->createNew();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');

        return $cart;
    }

    private function createProduct(string $code, bool $giftCard, bool $shippingRequired = false): Product
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode($code);
        $product->setName($code);
        $product->setSlug(strtolower($code));
        $product->setGiftCard($giftCard);
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode($code . '_VARIANT');
        $variant->setName($code);
        $variant->setShippingRequired($shippingRequired);

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $channel->getCode());
        $channelPricing->setPrice(self::CHANNEL_PRICE);
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
}
