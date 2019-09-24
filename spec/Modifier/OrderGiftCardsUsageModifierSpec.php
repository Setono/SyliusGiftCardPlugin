<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Modifier;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Modifier\OrderGiftCardsUsageModifier;
use Setono\SyliusGiftCardPlugin\Modifier\OrderGiftCardsUsageModifierInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderGiftCardsUsageModifierSpec extends ObjectBehavior
{
    function let(GiftCardCodeRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardCodeEntityManager): void
    {
        $this->beConstructedWith($giftCardCodeRepository, $giftCardCodeEntityManager);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardsUsageModifier::class);
    }

    function it_implements_order_gift_cards_usage_interface(): void
    {
        $this->shouldHaveType(OrderGiftCardsUsageModifierInterface::class);
    }

    function it_increments(
        OrderInterface $order,
        AdjustmentInterface $oneAdjustment,
        AdjustmentInterface $secondAdjustment,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        GiftCardCodeInterface $oneGiftCardCode,
        GiftCardCodeInterface $secondGiftCardCode
    ): void {
        $oneAdjustment->getOriginCode()->willReturn('code1');
        $oneAdjustment->getAmount()->willReturn(-100);
        $secondAdjustment->getOriginCode()->willReturn('code2');
        $secondAdjustment->getAmount()->willReturn(-50);
        $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->willReturn(new ArrayCollection([$oneAdjustment->getWrappedObject(), $secondAdjustment->getWrappedObject()]));
        $oneGiftCardCode->getAmount()->willReturn(100);
        $secondGiftCardCode->getAmount()->willReturn(100);
        $giftCardCodeRepository->findOneByCode('code1')->willReturn($oneGiftCardCode);
        $giftCardCodeRepository->findOneByCode('code2')->willReturn($secondGiftCardCode);

        $oneGiftCardCode->setAmount(0)->shouldBeCalled();
        $oneGiftCardCode->setActive(false)->shouldBeCalled();
        $oneGiftCardCode->addUsedInOrder($order)->shouldBeCalled();
        $secondGiftCardCode->setAmount(50)->shouldBeCalled();
        $secondGiftCardCode->setActive(true)->shouldBeCalled();
        $secondGiftCardCode->addUsedInOrder($order)->shouldBeCalled();

        $this->increment($order);
    }

    function it_decrements(
        OrderInterface $order,
        AdjustmentInterface $oneAdjustment,
        AdjustmentInterface $secondAdjustment,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        GiftCardCodeInterface $oneGiftCardCode,
        GiftCardCodeInterface $secondGiftCardCode
    ): void {
        $oneAdjustment->getOriginCode()->willReturn('code1');
        $oneAdjustment->getAmount()->willReturn(-100);
        $secondAdjustment->getOriginCode()->willReturn('code2');
        $secondAdjustment->getAmount()->willReturn(-50);
        $order->getAdjustments(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->willReturn(new ArrayCollection([$oneAdjustment->getWrappedObject(), $secondAdjustment->getWrappedObject()]));
        $oneGiftCardCode->getAmount()->willReturn(50);
        $secondGiftCardCode->getAmount()->willReturn(50);
        $giftCardCodeRepository->findOneByCode('code1')->willReturn($oneGiftCardCode);
        $giftCardCodeRepository->findOneByCode('code2')->willReturn($secondGiftCardCode);

        $oneGiftCardCode->setAmount(150)->shouldBeCalled();
        $oneGiftCardCode->setActive(true)->shouldBeCalled();
        $oneGiftCardCode->removeUsedInOrder($order)->shouldBeCalled();
        $secondGiftCardCode->setAmount(100)->shouldBeCalled();
        $secondGiftCardCode->setActive(true)->shouldBeCalled();
        $secondGiftCardCode->removeUsedInOrder($order)->shouldBeCalled();

        $this->decrement($order);
    }
}
