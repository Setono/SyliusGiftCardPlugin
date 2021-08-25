<?php

declare(strict_types=1);

namespace tests\Setono\SyliusGiftCardPlugin\Api\CommandHandler;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Api\Command\ConfigurationCodeAwareInterface;

class AssociateConfigurationToChannelTest extends TestCase
{
    public function testInstantiation(): void
    {
        $command = new AssociateConfigurationToChannel('locale_code', 'channel_code');

        $this->assertInstanceOf(ConfigurationCodeAwareInterface::class, $command);
    }

    public function testHasNullableConfigurationCode(): void
    {
        $command = new AssociateConfigurationToChannel('locale_code', 'channel_code');

        $this->assertNull($command->getConfigurationCode());
        $command->setConfigurationCode('configuration_code');
        $this->assertEquals('configuration_code', $command->getConfigurationCode());
    }
}
