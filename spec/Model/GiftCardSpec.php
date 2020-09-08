<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Model;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

class GiftCardSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCard::class);
    }

    public function it_is_a_resource(): void
    {
        $this->shouldHaveType(ResourceInterface::class);
    }

    public function it_implements_gift_card_interface(): void
    {
        $this->shouldHaveType(GiftCardInterface::class);
    }

    public function it_allows_access_via_properties(
        OrderItemUnitInterface $orderItemUnit,
        ChannelInterface $channel,
        OrderInterface $order
    ): void {
        $this->setOrderItemUnit($orderItemUnit);
        $this->getOrderItemUnit()->shouldReturn($orderItemUnit);

        $this->setCode('code');
        $this->getCode()->shouldReturn('code');

        $this->isEnabled()->shouldReturn(true);
        $this->disable();
        $this->isEnabled()->shouldReturn(false);

        $this->setAmount(100);
        $this->getAmount()->shouldReturn(100);

        $this->setCurrencyCode('USD');
        $this->getCurrencyCode()->shouldReturn('USD');

        $this->setChannel($channel);
        $this->getChannel()->shouldReturn($channel);
    }

    public function it_associates_used_in_orders(OrderInterface $order): void
    {
        $this->addAppliedOrder($order);
        $this->hasAppliedOrder($order)->shouldReturn(true);

        $this->removeAppliedOrder($order);
        $this->hasAppliedOrder($order)->shouldReturn(false);
    }
}
