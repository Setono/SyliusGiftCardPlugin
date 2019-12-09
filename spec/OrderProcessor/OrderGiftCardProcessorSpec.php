<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\OrderProcessor;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\OrderProcessor\OrderGiftCardProcessor;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderGiftCardProcessorSpec extends ObjectBehavior
{
    public function let(TranslatorInterface $translator, AdjustmentFactoryInterface $adjustmentFactory): void
    {
        $this->beConstructedWith($translator, $adjustmentFactory);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardProcessor::class);
    }

    public function it_implements_order_processor_interface(): void
    {
        $this->shouldHaveType(OrderProcessorInterface::class);
    }

    public function it_processes(
        TranslatorInterface $translator,
        OrderInterface $order,
        GiftCardInterface $giftCard1,
        GiftCardInterface $giftCard2,
        AdjustmentFactoryInterface $adjustmentFactory,
        AdjustmentInterface $adjustment1,
        AdjustmentInterface $adjustment2
    ): void {
        $order->getId()->willReturn(1);

        $order->isEmpty()->willReturn(false);
        $order->hasGiftCards()->willReturn(true);

        $giftCard1->getAmount()->willReturn(50);
        $giftCard1->getCode()->willReturn('gift-card-code-1');
        $giftCard2->getAmount()->willReturn(150);
        $giftCard2->getCode()->willReturn('gift-card-code-2');
        $order->getGiftCards()->willReturn(new ArrayCollection([$giftCard1->getWrappedObject(), $giftCard2->getWrappedObject()]));

        $order->getTotal()->willReturn(180, 130);

        $translator->trans(Argument::type('string'))->willReturn('Gift card');

        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            Argument::type('string'),
            -50
        )->willReturn($adjustment1);

        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            Argument::type('string'),
            -130
        )->willReturn($adjustment2);

        $adjustment1->setOriginCode('gift-card-code-1')->shouldBeCalled();
        $adjustment2->setOriginCode('gift-card-code-2')->shouldBeCalled();

        $order->addAdjustment($adjustment1)->shouldBeCalled();
        $order->addAdjustment($adjustment2)->shouldBeCalled();

        $this->process($order);
    }
}
