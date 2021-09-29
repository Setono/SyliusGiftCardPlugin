<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\CommandHandler;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Command\AddItemToCart as SetonoSyliusGiftCardAddItemToCart;
use Setono\SyliusGiftCardPlugin\Api\CommandHandler\AddItemToCartHandler;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart as SyliusAddItemToCart;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItemUnit;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Product;

final class AddItemToCartHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $productVariantRepository = $this->prophesize(ProductVariantRepositoryInterface::class);
        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardManager = $this->prophesize(EntityManagerInterface::class);

        $handler = new AddItemToCartHandler(
            $orderRepository->reveal(),
            $productVariantRepository->reveal(),
            $orderModifier->reveal(),
            $cartItemFactory->reveal(),
            $orderItemQuantityModifier->reveal(),
            $giftCardFactory->reveal(),
            $giftCardManager->reveal(),
        );

        $this->assertInstanceOf(AddItemToCartHandler::class, $handler);
    }

    /**
     * @test
     */
    public function it_adds_configurable_gift_card_to_cart(): void
    {
        $orderTokenValue = 'orderTokenValue';

        $orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $productVariantRepository = $this->prophesize(ProductVariantRepositoryInterface::class);
        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardManager = $this->prophesize(EntityManagerInterface::class);

        $product = new Product();
        $product->setGiftCard(true);
        $product->setGiftCardAmountConfigurable(true);
        $productVariant = new ProductVariant();
        $productVariant->setProduct($product);
        $cart = new Order();
        $cart->setTokenValue($orderTokenValue);
        $cartItem = new OrderItem();

        $productVariantRepository
            ->findOneBy(['code' => 'variant'])
            ->willReturn($productVariant);
        $orderRepository
            ->findCartByTokenValue($orderTokenValue)
            ->willReturn($cart);
        $cartItemFactory
            ->createNew()
            ->willReturn($cartItem);

        $orderItemQuantityModifier->modify($cartItem, 1)->shouldBeCalled();
        $orderModifier->addToOrder($cart, $cartItem)->shouldBeCalled();

        $cartItemUnit = new OrderItemUnit($cartItem);
        $cartItem->addUnit($cartItemUnit);

        $giftCard = new GiftCard();
        $giftCardFactory
            ->createFromOrderItemUnitAndCart($cartItemUnit, $cart)
            ->willReturn($giftCard);
        $giftCardManager->persist($giftCard)->shouldBeCalled();

        $handler = new AddItemToCartHandler(
            $orderRepository->reveal(),
            $productVariantRepository->reveal(),
            $orderModifier->reveal(),
            $cartItemFactory->reveal(),
            $orderItemQuantityModifier->reveal(),
            $giftCardFactory->reveal(),
            $giftCardManager->reveal(),
        );
        $message = new SetonoSyliusGiftCardAddItemToCart('variant', 1, 1500, 'Custom message');
        $message->setOrderTokenValue($orderTokenValue);
        $handler($message);
    }

    /**
     * @test
     */
    public function it_adds_simple_item_to_cart(): void
    {
        $orderTokenValue = 'orderTokenValue';

        $orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $productVariantRepository = $this->prophesize(ProductVariantRepositoryInterface::class);
        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardManager = $this->prophesize(EntityManagerInterface::class);

        $productVariant = $this->prophesize(ProductVariant::class);
        $cart = new Order();
        $cart->setTokenValue($orderTokenValue);
        $cartItem = $this->prophesize(OrderItem::class);
        $cartItem->setVariant($productVariant)->shouldBeCalled();

        $productVariantRepository
            ->findOneBy(['code' => 'variant'])
            ->willReturn($productVariant);
        $orderRepository
            ->findCartByTokenValue($orderTokenValue)
            ->willReturn($cart);
        $cartItemFactory
            ->createNew()
            ->willReturn($cartItem);

        $productVariant->getProduct()->shouldNotBeCalled();

        $orderItemQuantityModifier->modify($cartItem, 1)->shouldBeCalled();
        $orderModifier->addToOrder($cart, $cartItem)->shouldBeCalled();

        $cartItem->getUnits()->shouldNotBeCalled();

        $handler = new AddItemToCartHandler(
            $orderRepository->reveal(),
            $productVariantRepository->reveal(),
            $orderModifier->reveal(),
            $cartItemFactory->reveal(),
            $orderItemQuantityModifier->reveal(),
            $giftCardFactory->reveal(),
            $giftCardManager->reveal(),
        );
        $message = new SyliusAddItemToCart('variant', 1);
        $message->setOrderTokenValue($orderTokenValue);
        $handler($message);
    }

    /**
     * @test
     */
    public function it_adds_simple_gift_card_to_cart(): void
    {
        $orderTokenValue = 'orderTokenValue';

        $orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $productVariantRepository = $this->prophesize(ProductVariantRepositoryInterface::class);
        $orderModifier = $this->prophesize(OrderModifierInterface::class);
        $cartItemFactory = $this->prophesize(CartItemFactoryInterface::class);
        $orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $giftCardManager = $this->prophesize(EntityManagerInterface::class);

        $product = new Product();
        $product->setGiftCard(true);
        $product->setGiftCardAmountConfigurable(false);
        $productVariant = new ProductVariant();
        $productVariant->setProduct($product);
        $cart = new Order();
        $cart->setTokenValue($orderTokenValue);
        $cartItem = $this->prophesize(OrderItem::class);
        $cartItem->setVariant($productVariant)->shouldBeCalled();

        $productVariantRepository
            ->findOneBy(['code' => 'variant'])
            ->willReturn($productVariant);
        $orderRepository
            ->findCartByTokenValue($orderTokenValue)
            ->willReturn($cart);
        $cartItemFactory
            ->createNew()
            ->willReturn($cartItem);

        $cartItem->setUnitPrice(Argument::any())->shouldNotBeCalled();
        $cartItem->setImmutable(true)->shouldNotBeCalled();

        $orderItemQuantityModifier->modify($cartItem, 1)->shouldBeCalled();
        $orderModifier->addToOrder($cart, $cartItem)->shouldBeCalled();

        $cartItemUnit = new OrderItemUnit($cartItem->reveal());
        $cartItem->addUnit($cartItemUnit)->shouldBeCalled();
        $cartItem
            ->getUnits()
            ->willReturn(new ArrayCollection([$cartItemUnit]));

        $giftCard = new GiftCard();
        $giftCardFactory
            ->createFromOrderItemUnitAndCart($cartItemUnit, $cart)
            ->willReturn($giftCard);
        $giftCardManager->persist($giftCard)->shouldBeCalled();

        $handler = new AddItemToCartHandler(
            $orderRepository->reveal(),
            $productVariantRepository->reveal(),
            $orderModifier->reveal(),
            $cartItemFactory->reveal(),
            $orderItemQuantityModifier->reveal(),
            $giftCardFactory->reveal(),
            $giftCardManager->reveal(),
        );
        $message = new SetonoSyliusGiftCardAddItemToCart('variant', 1);
        $message->setOrderTokenValue($orderTokenValue);
        $handler($message);
    }
}
