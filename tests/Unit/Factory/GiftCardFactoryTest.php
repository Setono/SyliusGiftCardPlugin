<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\Order;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItem;
use Tests\Setono\SyliusGiftCardPlugin\Application\Model\OrderItemUnit;

final class GiftCardFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_with_code(): void
    {
        $giftCard = new GiftCard();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $giftCardCodeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $configurationProvider = $this->prophesize(GiftCardChannelConfigurationProviderInterface::class);

        $decoratedFactory->createNew()->willReturn($giftCard);
        $giftCardCodeGenerator->generate()->willReturn('super-code');

        $factory = new GiftCardFactory(
            $decoratedFactory->reveal(),
            $giftCardCodeGenerator->reveal(),
            $configurationProvider->reveal()
        );
        $createdGiftCard = $factory->createNew();

        $this->assertSame($giftCard, $createdGiftCard);
        $this->assertSame('super-code', $createdGiftCard->getCode());
    }

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_for_channel(): void
    {
        $giftCard = new GiftCard();
        $channel = new Channel();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $giftCardCodeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $configurationProvider = $this->prophesize(GiftCardChannelConfigurationProviderInterface::class);

        $decoratedFactory->createNew()->willReturn($giftCard);
        $giftCardCodeGenerator->generate()->willReturn('super-code');

        $factory = new GiftCardFactory(
            $decoratedFactory->reveal(),
            $giftCardCodeGenerator->reveal(),
            $configurationProvider->reveal()
        );
        $createdGiftCard = $factory->createForChannel($channel);

        $this->assertSame($giftCard, $createdGiftCard);
        $this->assertSame($channel, $createdGiftCard->getChannel());
    }

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_for_channel_from_admin(): void
    {
        $giftCard = new GiftCard();
        $channel = new Channel();

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $giftCardCodeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $configurationProvider = $this->prophesize(GiftCardChannelConfigurationProviderInterface::class);

        $decoratedFactory->createNew()->willReturn($giftCard);
        $giftCardCodeGenerator->generate()->willReturn('super-code');

        $factory = new GiftCardFactory(
            $decoratedFactory->reveal(),
            $giftCardCodeGenerator->reveal(),
            $configurationProvider->reveal()
        );
        $createdGiftCard = $factory->createForChannelFromAdmin($channel);

        $this->assertSame($giftCard, $createdGiftCard);
        $this->assertSame($channel, $createdGiftCard->getChannel());
        $this->assertSame(GiftCardInterface::ORIGIN_ADMIN, $createdGiftCard->getOrigin());
    }

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_for_order_item_unit_and_cart(): void
    {
        $giftCard = new GiftCard();
        $channel = new Channel();
        $currencyCode = 'USD';
        $cart = new Order();
        $cart->setChannel($channel);
        $cart->setCurrencyCode($currencyCode);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);
        $orderItemUnit->getTotal()->willReturn(1000);

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $giftCardCodeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $configurationProvider = $this->prophesize(GiftCardChannelConfigurationProviderInterface::class);

        $decoratedFactory->createNew()->willReturn($giftCard);
        $giftCardCodeGenerator->generate()->willReturn('super-code');

        $factory = new GiftCardFactory(
            $decoratedFactory->reveal(),
            $giftCardCodeGenerator->reveal(),
            $configurationProvider->reveal()
        );
        $createdGiftCard = $factory->createFromOrderItemUnitAndCart($orderItemUnit->reveal(), $cart);

        $orderItemUnit->setGiftCard($giftCard)->shouldBeCalled();
        $this->assertSame($giftCard, $createdGiftCard);
        $this->assertSame($channel, $createdGiftCard->getChannel());
        $this->assertSame($currencyCode, $createdGiftCard->getCurrencyCode());
        $this->assertSame($currencyCode, $createdGiftCard->getCurrencyCode());
        $this->assertSame(1000, $createdGiftCard->getAmount());
        $this->assertFalse($createdGiftCard->isEnabled());
        $this->assertSame(GiftCardInterface::ORIGIN_ORDER, $createdGiftCard->getOrigin());
    }

    /**
     * @test
     */
    public function it_creates_a_new_gift_card_for_order_item_unit(): void
    {
        $giftCard = new GiftCard();
        $channel = new Channel();
        $currencyCode = 'USD';
        $customer = new Customer();
        $cart = new Order();
        $cart->setChannel($channel);
        $cart->setCurrencyCode($currencyCode);
        $cart->setCustomer($customer);
        $orderItem = new OrderItem();
        $orderItem->setOrder($cart);
        $orderItemUnit = $this->prophesize(OrderItemUnit::class);
        $orderItemUnit->getTotal()->willReturn(1000);
        $orderItemUnit->getOrderItem()->willReturn($orderItem);

        $decoratedFactory = $this->prophesize(FactoryInterface::class);
        $giftCardCodeGenerator = $this->prophesize(GiftCardCodeGeneratorInterface::class);
        $configurationProvider = $this->prophesize(GiftCardChannelConfigurationProviderInterface::class);

        $decoratedFactory->createNew()->willReturn($giftCard);
        $giftCardCodeGenerator->generate()->willReturn('super-code');

        $factory = new GiftCardFactory(
            $decoratedFactory->reveal(),
            $giftCardCodeGenerator->reveal(),
            $configurationProvider->reveal()
        );
        $createdGiftCard = $factory->createFromOrderItemUnit($orderItemUnit->reveal());

        $orderItemUnit->setGiftCard($giftCard)->shouldBeCalled();
        $this->assertSame($giftCard, $createdGiftCard);
        $this->assertSame($channel, $createdGiftCard->getChannel());
        $this->assertSame($currencyCode, $createdGiftCard->getCurrencyCode());
        $this->assertSame($currencyCode, $createdGiftCard->getCurrencyCode());
        $this->assertSame(1000, $createdGiftCard->getAmount());
        $this->assertSame($customer, $createdGiftCard->getCustomer());
        $this->assertFalse($createdGiftCard->isEnabled());
        $this->assertSame(GiftCardInterface::ORIGIN_ORDER, $createdGiftCard->getOrigin());
    }
}
