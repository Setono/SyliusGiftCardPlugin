<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    /** @test */
    public function it_has_sensible_redemption_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'redemption' => ['mode' => 'adjustment', 'payment_method_code' => 'gift_card'],
        ], 'redemption');
    }

    /** @test */
    public function it_has_sensible_purchase_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'purchase' => ['minimum_amount' => 100, 'maximum_amount' => null],
        ], 'purchase');
    }

    /** @test */
    public function it_has_sensible_scalar_defaults(): void
    {
        $this->assertProcessedConfigurationEquals([[]], [
            'code_length' => 16,
        ], 'code_length');

        $this->assertProcessedConfigurationEquals([[]], [
            'default_validity_period' => '3 years',
        ], 'default_validity_period');
    }

    /** @test */
    public function it_only_allows_adjustment_or_payment_redemption_modes(): void
    {
        $this->assertPartialConfigurationIsInvalid(
            [['redemption' => ['mode' => 'invalid']]],
            'redemption.mode',
        );
    }

    /** @test */
    public function it_rejects_an_invalid_validity_period(): void
    {
        $this->assertConfigurationIsInvalid(
            [['default_validity_period' => 'not a period']],
            'strtotime',
        );
    }

    protected function getConfiguration(): ConfigurationInterface
    {
        return new Configuration();
    }
}
