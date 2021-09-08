<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\CommandHandler;

use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
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
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_associates_configuration_to_channel(): void
    {
        $configuration = new GiftCardConfiguration();
        $channel = new Channel();
        $locale = new Locale();
        $giftCardChannelConfiguration = new GiftCardChannelConfiguration();

        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->findOneBy(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->findOneByCode('super_channel')
            ->willReturn($channel);
        $localeRepository
            ->findOneBy(['code' => 'en_GB'])
            ->willReturn($locale);

        $giftCardChannelConfigurationRepository
            ->findOneBy([
                'configuration' => $configuration,
                'channel' => $channel,
                'locale' => $locale,
            ])
            ->willReturn(null);

        $giftCardChannelConfigurationFactory->createNew()->willReturn($giftCardChannelConfiguration);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $returnedConfiguration = $handler($command);

        self::assertEquals(1, $returnedConfiguration->getChannelConfigurations()->count());
        /** @var GiftCardChannelConfiguration $returnedChannelConfiguration */
        $returnedChannelConfiguration = $returnedConfiguration->getChannelConfigurations()->first();

        self::assertEquals($channel, $returnedChannelConfiguration->getChannel());
        self::assertEquals($locale, $returnedChannelConfiguration->getLocale());
    }

    /**
     * @test
     */
    public function it_does_nothing_if_association_already_exists(): void
    {
        $configuration = new GiftCardConfiguration();
        $channel = new Channel();
        $locale = new Locale();
        $existingGiftCardChannelConfiguration = new GiftCardChannelConfiguration();

        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->findOneBy(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->findOneByCode('super_channel')
            ->willReturn($channel);
        $localeRepository
            ->findOneBy(['code' => 'en_GB'])
            ->willReturn($locale);

        $giftCardChannelConfigurationRepository
            ->findOneBy([
                'configuration' => $configuration,
                'channel' => $channel,
                'locale' => $locale,
            ])
            ->willReturn(new ArrayCollection([$existingGiftCardChannelConfiguration]));

        $giftCardChannelConfigurationFactory->createNew()->shouldNotBeCalled();

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $returnedConfiguration = $handler($command);

        self::assertEquals(0, $returnedConfiguration->getChannelConfigurations()->count());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_configuration_code_empty(): void
    {
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $handler($command);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_configuration_not_found(): void
    {
        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $handler($command);
    }

    /**
     * @test
     */
    public function it_throws_eception_if_channel_not_found(): void
    {
        $configuration = new GiftCardConfiguration();

        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->findOneBy(['code' => 'super_configuration'])
            ->willReturn($configuration);

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $handler($command);
    }

    /**
     * @test
     */
    public function if_throws_exception_if_locale_not_found(): void
    {
        $configuration = new GiftCardConfiguration();
        $channel = new Channel();

        $giftCardConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationRepository = $this->prophesize(RepositoryInterface::class);
        $giftCardChannelConfigurationFactory = $this->prophesize(FactoryInterface::class);

        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository
            ->findOneBy(['code' => 'super_configuration'])
            ->willReturn($configuration);
        $channelRepository
            ->findOneByCode('super_channel')
            ->willReturn($channel);

        $this->expectException(InvalidArgumentException::class);

        $handler = new AssociateConfigurationToChannelHandler(
            $giftCardConfigurationRepository->reveal(),
            $channelRepository->reveal(),
            $localeRepository->reveal(),
            $giftCardChannelConfigurationRepository->reveal(),
            $giftCardChannelConfigurationFactory->reveal()
        );
        $handler($command);
    }
}
