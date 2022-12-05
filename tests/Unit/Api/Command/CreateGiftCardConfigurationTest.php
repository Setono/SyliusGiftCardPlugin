<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Command;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\CreateGiftCardConfiguration;

class CreateGiftCardConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);

        $this->assertInstanceOf(CreateGiftCardConfiguration::class, $command);
    }

    /**
     * @test
     */
    public function it_has_nullable_default_validity_period(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->defaultValidityPeriod);
    }

    /**
     * @test
     */
    public function it_has_nullable_page_size(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->pageSize);
    }

    /**
     * @test
     */
    public function it_has_nullable_orientation(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->orientation);
    }

    /**
     * @test
     */
    public function it_has_nullable_template(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->template);
    }
}
