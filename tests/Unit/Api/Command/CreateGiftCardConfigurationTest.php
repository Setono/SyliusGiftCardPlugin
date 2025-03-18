<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Api\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\CreateGiftCardConfiguration;

class CreateGiftCardConfigurationTest extends TestCase
{
    #[Test]
    public function it_is_initializable(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);

        $this->assertInstanceOf(CreateGiftCardConfiguration::class, $command);
    }

    #[Test]
    public function it_has_nullable_default_validity_period(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->defaultValidityPeriod);
    }

    #[Test]
    public function it_has_nullable_page_size(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->pageSize);
    }

    #[Test]
    public function it_has_nullable_orientation(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->orientation);
    }

    #[Test]
    public function it_has_nullable_template(): void
    {
        $command = new CreateGiftCardConfiguration('code', false, false);
        $this->assertNull($command->template);
    }
}
