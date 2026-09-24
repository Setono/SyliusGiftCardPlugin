<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardAwarePaymentMethodSelectionRequirementChecker;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Checker\OrderPaymentMethodSelectionRequirementCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;

/**
 * Redeeming leaves the order total alone, so Sylius' own checker sees an order that still costs something and
 * would send the customer to the payment step even when the gift cards settle all of it. This checker lets the
 * customer skip the step in exactly that case and leaves every other decision to Sylius
 */
final class GiftCardAwarePaymentMethodSelectionRequirementCheckerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<OrderPaymentMethodSelectionRequirementCheckerInterface> */
    private ObjectProphecy $decorated;

    /** @var ObjectProphecy<GiftCardCoverageCalculatorInterface> */
    private ObjectProphecy $coverageCalculator;

    protected function setUp(): void
    {
        $this->decorated = $this->prophesize(OrderPaymentMethodSelectionRequirementCheckerInterface::class);
        $this->coverageCalculator = $this->prophesize(GiftCardCoverageCalculatorInterface::class);
    }

    /**
     * @test
     *
     * @dataProvider coveragesSettlingTheOrder
     */
    public function it_does_not_require_a_payment_method_when_the_gift_cards_settle_the_order(int $covered): void
    {
        $order = $this->order(10000);
        $this->coverageCalculator->calculate($order)->willReturn($this->coverage($covered));

        // Sylius would ask for a payment method, as the order still costs something
        $this->decorated->isPaymentMethodSelectionRequired(Argument::any())->willReturn(true);

        self::assertFalse($this->checker()->isPaymentMethodSelectionRequired($order));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function coveragesSettlingTheOrder(): iterable
    {
        yield 'the gift cards cover the order exactly' => [10000];
        yield 'the gift cards cover more than the order' => [10001];
    }

    /**
     * @test
     *
     * @dataProvider decisions
     */
    public function it_leaves_the_decision_to_sylius_when_something_is_left_to_pay(bool $required): void
    {
        $order = $this->order(10000);
        $this->coverageCalculator->calculate($order)->willReturn($this->coverage(9999));
        $this->decorated->isPaymentMethodSelectionRequired($order)->willReturn($required);

        self::assertSame($required, $this->checker()->isPaymentMethodSelectionRequired($order));
    }

    /**
     * @test
     *
     * @dataProvider decisions
     */
    public function it_leaves_the_decision_to_sylius_for_an_order_that_cannot_carry_gift_cards(bool $required): void
    {
        $order = $this->prophesize(CoreOrderInterface::class)->reveal();

        $this->coverageCalculator->calculate(Argument::any())->shouldNotBeCalled();
        $this->decorated->isPaymentMethodSelectionRequired($order)->willReturn($required);

        self::assertSame($required, $this->checker()->isPaymentMethodSelectionRequired($order));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function decisions(): iterable
    {
        yield 'Sylius requires a payment method' => [true];
        yield 'Sylius does not require a payment method' => [false];
    }

    private function checker(): GiftCardAwarePaymentMethodSelectionRequirementChecker
    {
        return new GiftCardAwarePaymentMethodSelectionRequirementChecker(
            $this->decorated->reveal(),
            $this->coverageCalculator->reveal(),
        );
    }

    private function order(int $total): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getTotal()->willReturn($total);

        return $order->reveal();
    }

    private function coverage(int $amount): GiftCardCoverage
    {
        return new GiftCardCoverage([
            ['giftCard' => $this->prophesize(GiftCardInterface::class)->reveal(), 'amount' => $amount],
        ]);
    }
}
