<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
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
        /** @var GiftCardInterface $giftCardCode */
        $giftCardCode = $this->decoratedFactory->createNew();

        return $giftCardCode;
    }

    public function createForChannel(ChannelInterface $channel): GiftCardInterface
    {
        $giftCardCode = $this->createNew();
        $giftCardCode->setChannel($channel);

        return $giftCardCode;
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

        $giftCardCode = $this->createNew();
        $giftCardCode->setOrderItemUnit($orderItemUnit);
        $giftCardCode->setChannel($channel);
        $giftCardCode->setAmount($orderItemUnit->getTotal());
        $giftCardCode->setCurrencyCode($currencyCode);
        $giftCardCode->setInitialAmount($orderItemUnit->getTotal());
        $giftCardCode->setCode($this->giftCardCodeGenerator->generate());
        $giftCardCode->disable();

        return $giftCardCode;
    }
}
