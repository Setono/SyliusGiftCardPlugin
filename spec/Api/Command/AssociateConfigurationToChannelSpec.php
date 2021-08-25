<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Api\Command;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Api\Command\ConfigurationCodeAwareInterface;

final class AssociateConfigurationToChannelSpec extends ObjectBehavior
{
    public function let(): void
    {
        $this->beConstructedWith('en_GB', 'super_channel');
    }

    public function it_is_initialisable(): void
    {
        $this->shouldBeAnInstanceOf(AssociateConfigurationToChannel::class);
    }

    public function it_implements_configuration_code_aware_interface(): void
    {
        $this->shouldImplement(ConfigurationCodeAwareInterface::class);
    }

    public function it_has_nullable_configuration_code(): void
    {
        $this->getConfigurationCode()->shouldReturn(null);

        $this->setConfigurationCode('super_code');
        $this->getConfigurationCode()->shouldReturn('super_code');
    }
}
