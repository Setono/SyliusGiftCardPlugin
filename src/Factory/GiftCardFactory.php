<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardFactory implements GiftCardFactoryInterface
{
    /** @var FactoryInterface */
    private $decoratedFactory;

    /** @var GiftCardCodeGeneratorInterface */
    private $giftCardCodeGenerator;

    public function __construct(FactoryInterface $decoratedFactory, GiftCardCodeGeneratorInterface $giftCardCodeGenerator)
    {
        $this->decoratedFactory = $decoratedFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
    }

    public function createNew(): GiftCardInterface
    {
        /** @var GiftCardInterface $giftCard */
        $giftCard = $this->decoratedFactory->createNew();

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

        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();

        Assert::isInstanceOf($channel, ChannelInterface::class);

        $currencyCode = $order->getCurrencyCode();
        Assert::notNull($currencyCode);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        Assert::isInstanceOf($customer, CustomerInterface::class);

        $giftCard = $this->createNew();
        $giftCard->setCustomer($customer);
        $giftCard->setOrderItemUnit($orderItemUnit);
        $giftCard->setChannel($channel);
        $giftCard->setAmount($orderItemUnit->getTotal());
        $giftCard->setCurrencyCode($currencyCode);
        $giftCard->setInitialAmount($orderItemUnit->getTotal());
        $giftCard->setCode($this->giftCardCodeGenerator->generate());
        $giftCard->disable();

        return $giftCard;
    }
}
