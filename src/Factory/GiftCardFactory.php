<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardFactory implements GiftCardFactoryInterface
{
    private FactoryInterface $decoratedFactory;

    private GiftCardCodeGeneratorInterface $giftCardCodeGenerator;

    public function __construct(FactoryInterface $decoratedFactory, GiftCardCodeGeneratorInterface $giftCardCodeGenerator)
    {
        $this->decoratedFactory = $decoratedFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
    }

    public function createNew(): GiftCardInterface
    {
        /** @var GiftCardInterface $giftCard */
        $giftCard = $this->decoratedFactory->createNew();
        $giftCard->setCode($this->giftCardCodeGenerator->generate());

        return $giftCard;
    }

    public function createForChannel(ChannelInterface $channel): GiftCardInterface
    {
        $giftCard = $this->createNew();
        $giftCard->setChannel($channel);

        return $giftCard;
    }

    public function createFromOrderItemUnit(OrderItemUnitInterface $orderItemUnit): GiftCardInterface
    {
        /** @var OrderInterface|null $order */
        $order = $orderItemUnit->getOrderItem()->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        Assert::isInstanceOf($customer, CustomerInterface::class);

        $giftCard = $this->createFromOrderItemUnitAndCart($orderItemUnit, $order);
        $giftCard->setCustomer($customer);

        return $giftCard;
    }

    public function createFromOrderItemUnitAndCart(
        OrderItemUnitInterface $orderItemUnit,
        OrderInterface $cart
    ): GiftCardInterface {
        $channel = $cart->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);
        $currencyCode = $cart->getCurrencyCode();
        Assert::notNull($currencyCode);

        $giftCard = $this->createNew();
        $giftCard->setOrderItemUnit($orderItemUnit);
        $giftCard->setAmount($orderItemUnit->getTotal());
        $giftCard->setCurrencyCode($currencyCode);
        $giftCard->setChannel($channel);
        $giftCard->disable();

        return $giftCard;
    }
}
