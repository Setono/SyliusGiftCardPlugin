<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\PHPUnit\Api\Command;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Api\Command\ConfigurationCodeAwareInterface;

class AssociateConfigurationToChannelTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $command = new AssociateConfigurationToChannel('locale_code', 'channel_code');

        $this->assertInstanceOf(ConfigurationCodeAwareInterface::class, $command);
    }

    /**
     * @test
     */
    public function it_has_nullable_configuration_code(): void
    {
        $command = new AssociateConfigurationToChannel('locale_code', 'channel_code');

        $this->assertNull($command->getConfigurationCode());
        $command->setConfigurationCode('configuration_code');
        $this->assertEquals('configuration_code', $command->getConfigurationCode());
    }
}
