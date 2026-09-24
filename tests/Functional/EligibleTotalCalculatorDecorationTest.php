<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Tests\Application\Calculator\OverridableEligibleTotalCalculator;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The README tells applications to decorate EligibleTotalCalculatorInterface to change what gift cards may pay for.
 * The test application does exactly that (config/services_test.yaml), so this proves the decorator is the
 * calculator the plugin's own services end up with
 */
final class EligibleTotalCalculatorDecorationTest extends KernelTestCase
{
    /** @test */
    public function it_covers_the_eligible_total_a_decorator_of_the_interface_returns(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $eligibleTotalCalculator = $container->get(EligibleTotalCalculatorInterface::class);
        self::assertInstanceOf(OverridableEligibleTotalCalculator::class, $eligibleTotalCalculator);
        $eligibleTotalCalculator->override(2500);

        $giftCard = new GiftCard();
        $giftCard->setCurrencyCode('USD');
        $giftCard->setAmount(5000);
        $giftCard->enable();

        $order = new Order();
        $order->setCurrencyCode('USD');
        $order->addGiftCard($giftCard);

        $coverageCalculator = $container->get(GiftCardCoverageCalculatorInterface::class);
        self::assertInstanceOf(GiftCardCoverageCalculatorInterface::class, $coverageCalculator);

        // The order has no items, so the plugin's own calculator would let the gift card cover nothing
        self::assertSame(2500, $coverageCalculator->calculate($order)->getTotal());
    }
}
