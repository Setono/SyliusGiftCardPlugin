<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\OrderProcessor;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\OrderProcessor\GiftCardAdjustmentProcessor;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GiftCardAdjustmentProcessorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Sylius' own adjustments clearer could only be told about the gift card adjustment type from 1.14.2
     * onwards, so the processor has to clear what it previously added itself. Without this the adjustments
     * accumulate on every processing run on the Sylius versions that predate that parameter.
     *
     * @test
     */
    public function it_clears_previously_added_gift_card_adjustments_before_adding_new_ones(): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getCode()->willReturn('ABCD');

        $order = $this->orderWithGiftCards(true);
        $adjustment = $this->prophesize(AdjustmentInterface::class);
        $adjustment->setOriginCode('ABCD')->shouldBeCalled();

        $factory = $this->prophesize(AdjustmentFactoryInterface::class);
        $factory->createWithData(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT, 'Gift card', -2500)
            ->willReturn($adjustment->reveal());

        $processor = new GiftCardAdjustmentProcessor(
            $this->translator(),
            $factory->reveal(),
            $this->coverage([['giftCard' => $giftCard->reveal(), 'amount' => 2500]]),
        );

        $processor->process($order->reveal());

        $order->removeAdjustmentsRecursively(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->shouldHaveBeenCalled();
        $order->addAdjustment($adjustment->reveal())->shouldHaveBeenCalledOnce();
    }

    /**
     * Clearing has to happen before the early return, otherwise adjustments survive removing the last gift card
     *
     * @test
     */
    public function it_clears_gift_card_adjustments_even_when_no_gift_cards_are_applied(): void
    {
        $order = $this->orderWithGiftCards(false);

        $factory = $this->prophesize(AdjustmentFactoryInterface::class);
        $factory->createWithData(Argument::cetera())->shouldNotBeCalled();

        $processor = new GiftCardAdjustmentProcessor(
            $this->translator(),
            $factory->reveal(),
            $this->coverage([]),
        );

        $processor->process($order->reveal());

        $order->removeAdjustmentsRecursively(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT)->shouldHaveBeenCalled();
        $order->addAdjustment(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * @return ObjectProphecy<OrderInterface>
     */
    private function orderWithGiftCards(bool $hasGiftCards): ObjectProphecy
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->isEmpty()->willReturn(false);
        $order->hasGiftCards()->willReturn($hasGiftCards);

        return $order;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('setono_sylius_gift_card.ui.gift_card')->willReturn('Gift card');

        return $translator->reveal();
    }

    /**
     * @param list<array{giftCard: GiftCardInterface, amount: int}> $entries
     */
    private function coverage(array $entries): GiftCardCoverageCalculatorInterface
    {
        $calculator = $this->prophesize(GiftCardCoverageCalculatorInterface::class);
        $calculator->calculate(Argument::any())->willReturn(new GiftCardCoverage($entries));

        return $calculator->reveal();
    }
}
