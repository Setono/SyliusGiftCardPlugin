<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Application\Calculator;

use Setono\SyliusGiftCardPlugin\Calculator\EligibleTotalCalculatorInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Decorates EligibleTotalCalculatorInterface in the test environment the way an application would. It passes
 * through to the plugin's calculator until a test overrides the eligible total, so every other test sees the
 * default behaviour
 */
final class OverridableEligibleTotalCalculator implements EligibleTotalCalculatorInterface
{
    private ?int $eligibleTotal = null;

    public function __construct(private readonly EligibleTotalCalculatorInterface $decorated)
    {
    }

    public function override(int $eligibleTotal): void
    {
        $this->eligibleTotal = $eligibleTotal;
    }

    public function getEligibleTotal(OrderInterface $order): int
    {
        return $this->eligibleTotal ?? $this->decorated->getEligibleTotal($order);
    }
}
