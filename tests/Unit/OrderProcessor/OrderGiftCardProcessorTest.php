<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\OrderProcessor;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\OrderProcessor\OrderGiftCardProcessor;
use Setono\SyliusGiftCardPlugin\Provider\OrderEligibleTotalProviderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderGiftCardProcessorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);
        $orderEligibleTotalProvider = $this->prophesize(OrderEligibleTotalProviderInterface::class);

        $processor = new OrderGiftCardProcessor($translator->reveal(), $adjustmentFactory->reveal(), $orderEligibleTotalProvider->reveal());
        $this->assertInstanceOf(OrderGiftCardProcessor::class, $processor);
        $this->assertInstanceOf(OrderProcessorInterface::class, $processor);
    }

    /**
     * @test
     */
    public function it_processes(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);
        $orderEligibleTotalProvider = $this->prophesize(OrderEligibleTotalProviderInterface::class);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(1);
        $order->isEmpty()->willReturn(false);
        $order->hasGiftCards()->willReturn(true);

        $giftCard1 = $this->prophesize(GiftCardInterface::class);
        $giftCard1->getAmount()->willReturn(50);
        $giftCard1->getCode()->willReturn('gift-card-code-1');
        $giftCard2 = $this->prophesize(GiftCardInterface::class);
        $giftCard2->getAmount()->willReturn(150);
        $giftCard2->getCode()->willReturn('gift-card-code-2');
        $order->getGiftCards()->willReturn(new ArrayCollection([$giftCard1->reveal(), $giftCard2->reveal()]));

        $orderEligibleTotalProvider->getEligibleTotal($order->reveal(), Argument::type(GiftCardInterface::class))->willReturn(180, 130);

        $translator->trans(Argument::type('string'))->willReturn('Gift card');

        $adjustment1 = $this->prophesize(AdjustmentInterface::class);
        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            Argument::type('string'),
            -50
        )->willReturn($adjustment1);

        $adjustment2 = $this->prophesize(AdjustmentInterface::class);
        $adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
            Argument::type('string'),
            -130
        )->willReturn($adjustment2);

        $adjustment1->setOriginCode('gift-card-code-1')->shouldBeCalled();
        $adjustment2->setOriginCode('gift-card-code-2')->shouldBeCalled();

        $order->addAdjustment($adjustment1)->shouldBeCalled();
        $order->addAdjustment($adjustment2)->shouldBeCalled();

        $processor = new OrderGiftCardProcessor($translator->reveal(), $adjustmentFactory->reveal(), $orderEligibleTotalProvider->reveal());
        $processor->process($order->reveal());
    }
}
