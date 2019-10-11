<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifier;
use Setono\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifierInterface;

final class OrderGiftCardAmountModifierSpec extends ObjectBehavior
{
    public function let(
        ObjectManager $giftCardCodeManager
    ): void {
        $this->beConstructedWith($giftCardCodeManager);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardAmountModifier::class);
    }

    public function it_implements_order_gift_cards_usage_interface(): void
    {
        $this->shouldHaveType(OrderGiftCardAmountModifierInterface::class);
    }

    public function it_decrements_amount(
        OrderInterface $order,
        AdjustmentInterface $adjustment,
        GiftCardInterface $giftCard
    ): void {
        $adjustment->getAmount()->willReturn(150);
        $adjustment->getOriginCode()->willReturn('gift-card-1');

        $giftCard->getCode()->willReturn('gift-card-1');
        $giftCard->getAmount()->willReturn(200);
        $giftCard->enable()->shouldBeCalled();
        $giftCard->setAmount(50)->shouldBeCalled();

        $order
            ->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)
            ->willReturn(new ArrayCollection([$adjustment->getWrappedObject()]))
        ;

        $order
            ->getGiftCards()
            ->willReturn(new ArrayCollection([$giftCard->getWrappedObject()]))
        ;

        $this->decrement($order);
    }

    public function it_decrements_amount_and_disables_gift_card_when_amount_is_zero(
        OrderInterface $order,
        AdjustmentInterface $adjustment,
        GiftCardInterface $giftCard
    ): void {
        $adjustment->getAmount()->willReturn(200);
        $adjustment->getOriginCode()->willReturn('gift-card-1');

        $giftCard->getCode()->willReturn('gift-card-1');
        $giftCard->getAmount()->willReturn(200);
        $giftCard->disable()->shouldBeCalled();
        $giftCard->setAmount(0)->shouldBeCalled();

        $order
            ->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)
            ->willReturn(new ArrayCollection([$adjustment->getWrappedObject()]))
        ;

        $order
            ->getGiftCards()
            ->willReturn(new ArrayCollection([$giftCard->getWrappedObject()]))
        ;

        $this->decrement($order);
    }

    public function it_increments_amount(
        OrderInterface $order,
        AdjustmentInterface $adjustment,
        GiftCardInterface $giftCard
    ): void {
        $adjustment->getAmount()->willReturn(150);
        $adjustment->getOriginCode()->willReturn('gift-card-1');

        $giftCard->getCode()->willReturn('gift-card-1');
        $giftCard->getAmount()->willReturn(200);
        $giftCard->enable()->shouldBeCalled();
        $giftCard->setAmount(350)->shouldBeCalled();

        $order
            ->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)
            ->willReturn(new ArrayCollection([$adjustment->getWrappedObject()]))
        ;

        $order
            ->getGiftCards()
            ->willReturn(new ArrayCollection([$giftCard->getWrappedObject()]))
        ;

        $this->increment($order);
    }
}
