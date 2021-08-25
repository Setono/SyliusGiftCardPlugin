<?php

declare(strict_types=1);

namespace tests\Setono\SyliusGiftCardPlugin\Api\CommandHandler;

use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Api\CommandHandler\AssociateConfigurationToChannelHandler;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class AssociateConfigurationToChannelHandlerTest extends TestCase
{
    public function testAssociatesConfigurationToChannel(): void
    {
        $configuration = $this->createMock(GiftCardConfiguration::class);
        $channel = $this->createMock(Channel::class);
        $locale = $this->createMock(Locale::class);
        $giftCardChannelConfiguration = $this->createMock(GiftCardChannelConfiguration::class);

        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->method('findOneBy')
            ->with(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->method('findOneByCode')
            ->with('super_channel')
            ->willReturn($channel);
        $localeRepository
            ->method('findOneBy')
            ->with(['code' => 'en_GB'])
            ->willReturn($locale);

        $giftCardChannelConfigurationRepository
            ->method('findOneBy')
            ->with([
                'configuration' => $configuration,
                'channel' => $channel,
                'locale' => $locale,
            ])
            ->willReturn(null);

        $giftCardChannelConfigurationFactory->method('createNew')->willReturn($giftCardChannelConfiguration);

        $giftCardChannelConfiguration
            ->expects($this->once())
            ->method('setConfiguration')
            ->with($this->equalTo($configuration))
        ;
        $giftCardChannelConfiguration
            ->expects($this->once())
            ->method('setChannel')
            ->with($this->equalTo($channel))
        ;
        $giftCardChannelConfiguration
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo($locale))
        ;

        $configuration
            ->expects($this->once())
            ->method('addChannelConfiguration')
            ->with($this->equalTo($giftCardChannelConfiguration));

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }

    public function testDoesNothingIfAssociationAlreadyExists(): void
    {
        $configuration = $this->createMock(GiftCardConfiguration::class);
        $channel = $this->createMock(Channel::class);
        $locale = $this->createMock(Locale::class);
        $existingGiftCardChannelConfiguration = $this->createMock(GiftCardChannelConfiguration::class);

        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->method('findOneBy')
            ->with(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->method('findOneByCode')
            ->with('super_channel')
            ->willReturn($channel);
        $localeRepository
            ->method('findOneBy')
            ->with(['code' => 'en_GB'])
            ->willReturn($locale);

        $giftCardChannelConfigurationRepository
            ->method('findOneBy')
            ->with([
                'configuration' => $configuration,
                'channel' => $channel,
                'locale' => $locale,
            ])
            ->willReturn(new ArrayCollection([$existingGiftCardChannelConfiguration]));

        $giftCardChannelConfigurationFactory
            ->expects($this->never())
            ->method('createNew');

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }

    public function testThrowsExceptionIfConfigurationCodeEmpty(): void
    {
        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }

    public function testThrowsExceptionIfConfigurationNotFound(): void
    {
        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }

    public function testThrowsExceptionIfChannelNotFound(): void
    {
        $configuration = $this->createMock(GiftCardConfiguration::class);

        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->method('findOneBy')
            ->with(['code' => 'super_configuration'])
            ->willReturn($configuration);

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }

    public function testThrowsExceptionIfLocaleNotFound(): void
    {
        $configuration = $this->createMock(GiftCardConfiguration::class);
        $channel = $this->createMock(Channel::class);

        $giftCardConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $localeRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->createMock(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->createMock(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->method('findOneBy')
            ->with(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->method('findOneByCode')
            ->with('super_channel')
            ->willReturn($channel);

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
        $handler($command);
    }
}
