<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\OrderProcessing;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\OrderProcessing\OrderGiftCardProcessor;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderGiftCardProcessorSpec extends ObjectBehavior
{
    function let(GiftCardCodeRepositoryInterface $giftCardCodeRepository, AdjustmentFactoryInterface $adjustmentFactory): void
    {
        $this->beConstructedWith($giftCardCodeRepository, $adjustmentFactory);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardProcessor::class);
    }

    function it_implements_order_processor_interface(): void
    {
        $this->shouldHaveType(OrderProcessorInterface::class);
    }

    function it_processes(
        OrderInterface $order,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        GiftCardCodeInterface $oneGiftCardCode,
        GiftCardCodeInterface $secondGiftCardCode,
        AdjustmentFactoryInterface $adjustmentFactory,
        AdjustmentInterface $oneAdjustment,
        AdjustmentInterface $secondAdjustment,
        OrderItemInterface $orderItem
    ): void {
        $orderItem->getProductName()->willReturn('Gift card');
        $oneGiftCardCode->getAmount()->willReturn(50);
        $oneGiftCardCode->getCode()->willReturn('code1');
        $oneGiftCardCode->getOrderItem()->willReturn($orderItem);
        $secondGiftCardCode->getAmount()->willReturn(150);
        $secondGiftCardCode->getCode()->willReturn('code2');
        $secondGiftCardCode->getOrderItem()->willReturn($orderItem);
        $order->getId()->willReturn(1);
        $order->getTotal()->willReturn(100);
        $giftCardCodeRepository->findActiveByCurrentOrder($order)->willReturn([$oneGiftCardCode, $secondGiftCardCode]);
        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            'Gift card',
            -50
        )->willReturn($oneAdjustment);
        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            'Gift card',
            -100
        )->willReturn($secondAdjustment);

        $order->removeAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->shouldBeCalled();
        $oneAdjustment->setOriginCode('code1')->shouldBeCalled();
        $secondAdjustment->setOriginCode('code2')->shouldBeCalled();
        $oneAdjustment->setAdjustable($order)->shouldBeCalled();
        $secondAdjustment->setAdjustable($order)->shouldBeCalled();
        $order->addAdjustment($oneAdjustment)->shouldBeCalled();
        $order->addAdjustment($secondAdjustment)->shouldBeCalled();

        $this->process($order);
    }
}
