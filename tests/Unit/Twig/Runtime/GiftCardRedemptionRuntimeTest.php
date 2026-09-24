<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardRedemptionRuntime;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * The cart prints what the gift cards cover and what is left to pay from these functions. The figures come from
 * the configured redemption method, so an application that substitutes its own sees its figures in the cart
 */
final class GiftCardRedemptionRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardRedemptionMethodInterface> */
    private ObjectProphecy $redemptionMethod;

    protected function setUp(): void
    {
        $this->redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
    }

    /** @test */
    public function it_tells_what_the_redemption_method_says_the_gift_cards_cover(): void
    {
        $order = $this->order(10000);
        $giftCard = $this->prophesize(GiftCardInterface::class)->reveal();

        $this->redemptionMethod->getCoveredAmount($order)->willReturn(6000);
        $this->redemptionMethod->getCoveredAmountByGiftCard($order, $giftCard)->willReturn(2500);

        self::assertSame(6000, $this->runtime()->getCoveredAmount($order));
        self::assertSame(2500, $this->runtime()->getCoveredAmountByGiftCard($order, $giftCard));
    }

    /**
     * Redeeming leaves the order total alone, so what is left to pay is the total less what the cards cover
     *
     * @test
     *
     * @dataProvider coverages
     */
    public function it_tells_what_is_left_to_pay_after_the_gift_cards(int $covered, int $remaining): void
    {
        $order = $this->order(10000);
        $this->redemptionMethod->getCoveredAmount($order)->willReturn($covered);

        self::assertSame($remaining, $this->runtime()->getRemainingTotal($order));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function coverages(): iterable
    {
        yield 'no coverage' => [0, 10000];
        yield 'part of the order' => [6000, 4000];
        yield 'the whole order' => [10000, 0];
        // never a negative amount to pay, whatever a substituted redemption method reports
        yield 'more than the order' => [12000, 0];
    }

    private function runtime(): GiftCardRedemptionRuntime
    {
        return new GiftCardRedemptionRuntime(
            $this->redemptionMethod->reveal(),
            $this->prophesize(FormFactoryInterface::class)->reveal(),
        );
    }

    private function order(int $total): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn($total);

        return $order->reveal();
    }
}
