<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Entity;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCode;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

class GiftCardCodeSpec extends ObjectBehavior
{
    function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardCode::class);
    }

    function it_is_a_resource(): void
    {
        $this->shouldHaveType(ResourceInterface::class);
    }

    function it_implements_gift_card_interface(): void
    {
        $this->shouldHaveType(GiftCardCodeInterface::class);
    }

    function it_allows_access_via_properties(
        OrderItemInterface $orderItem,
        GiftCardInterface $giftCard,
        OrderInterface $order
    ): void {
        $this->setOrderItem($orderItem);
        $this->getOrderItem()->shouldReturn($orderItem);

        $this->setCurrentOrder($order);
        $this->getCurrentOrder()->shouldReturn($order);

        $this->setCode('code');
        $this->getCode()->shouldReturn('code');

        $this->setGiftCard($giftCard);
        $this->getGiftCard()->shouldReturn($giftCard);

        $this->isActive()->shouldReturn(false);
        $this->setIsActive(true);
        $this->isActive()->shouldReturn(true);

        $this->setAmount(100);
        $this->getAmount()->shouldReturn(100);

        $this->setChannelCode('web');
        $this->getChannelCode()->shouldReturn('web');
    }

    function it_associates_used_in_orders(OrderInterface $order): void
    {
        $this->addUsedInOrder($order);
        $this->hasUsedInOrder($order)->shouldReturn(true);

        $this->removeUsedInOrder($order);
        $this->hasUsedInOrder($order)->shouldReturn(false);
    }
}
