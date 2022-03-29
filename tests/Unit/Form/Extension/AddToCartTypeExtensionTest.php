<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Form\Extension;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Form\Extension\AddToCartTypeExtension;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\FormEvent;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItemUnit;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Product;

final class AddToCartTypeExtensionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_populates_cart_item_for_configurable_gift_card(): void
    {
        $cart = $this->prophesize(Order::class);
        $orderItem = $this->prophesize(OrderItem::class);
        $giftCardInformation = $this->prophesize(GiftCardInformationInterface::class);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);

        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->reveal()]));

        $product = $this->prophesize(Product::class);
        $product->isGiftCard()->willReturn(true);
        $product->isGiftCardAmountConfigurable()->willReturn(true);
        $orderItem->getProduct()->willReturn($product);

        $giftCardInformation->getAmount()->willReturn(100);
        $giftCardInformation->getCustomMessage()->willReturn('custom message');

        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $formEvent = $this->prophesize(FormEvent::class);
        $formEvent->getData()->willReturn(new AddToCartCommand(
            $cart->reveal(),
            $orderItem->reveal(),
            $giftCardInformation->reveal()
        ));

        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCardFactory->createFromOrderItemUnitAndCart($orderItemUnit, $cart)->willReturn($giftCard);
        $giftCard->setCustomMessage('custom message')->shouldBeCalled();

        $orderItem->setUnitPrice(100)->shouldBeCalled();
        $orderItem->setImmutable(true)->shouldBeCalled();

        $extension = new AddToCartTypeExtension($giftCardFactory->reveal(), $entityManager->reveal());
        $extension->populateCartItem($formEvent->reveal());
    }

    /**
     * @test
     */
    public function it_populates_cart_item_for_not_configurable_gift_card(): void
    {
        $cart = $this->prophesize(Order::class);
        $orderItem = $this->prophesize(OrderItem::class);
        $giftCardInformation = $this->prophesize(GiftCardInformationInterface::class);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);

        $channel = $this->prophesize(ChannelInterface::class);
        $variant = $this->prophesize(ProductVariantInterface::class);
        $channelPricing = $this->prophesize(ChannelPricingInterface::class);

        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->reveal()]));

        $product = $this->prophesize(Product::class);
        $product->isGiftCard()->willReturn(true);
        $product->isGiftCardAmountConfigurable()->willReturn(false);
        $orderItem->getProduct()->willReturn($product);
        $cart->getChannel()->willReturn($channel);
        $orderItem->getVariant()->willReturn($variant);
        $variant->getChannelPricingForChannel($channel)->willReturn($channelPricing);
        $channelPricing->getPrice()->willReturn(100);

        $giftCardFactory = $this->prophesize(GiftCardFactoryInterface::class);
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $formEvent = $this->prophesize(FormEvent::class);
        $formEvent->getData()->willReturn(new AddToCartCommand(
            $cart->reveal(),
            $orderItem->reveal(),
            $giftCardInformation->reveal()
        ));

        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCardFactory->createFromOrderItemUnitAndCart($orderItemUnit, $cart)->willReturn($giftCard);
        $orderItem->setUnitPrice(100)->shouldBeCalled();

        $extension = new AddToCartTypeExtension($giftCardFactory->reveal(), $entityManager->reveal());
        $extension->populateCartItem($formEvent->reveal());
    }
}
