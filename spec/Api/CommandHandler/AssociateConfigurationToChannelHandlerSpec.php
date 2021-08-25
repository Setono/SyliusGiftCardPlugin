<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Api\CommandHandler;

use InvalidArgumentException;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class AssociateConfigurationToChannelHandlerSpec extends ObjectBehavior
{
    public function let(
        RepositoryInterface $giftCardConfigurationRepository,
        ChannelRepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository,
        RepositoryInterface $giftCardChannelConfigurationRepository,
        FactoryInterface $giftCardChannelConfigurationFactory
    ): void {
        $this->beConstructedWith(
            $giftCardConfigurationRepository,
            $channelRepository,
            $localeRepository,
            $giftCardChannelConfigurationRepository,
            $giftCardChannelConfigurationFactory
        );
    }

    public function it_is_a_message_handler(): void
    {
        $this->shouldImplement(MessageHandlerInterface::class);
    }

    public function it_associates_configuration_to_channel(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationInterface $configuration,
        ChannelRepositoryInterface $channelRepository,
        ChannelInterface $channel,
        RepositoryInterface $localeRepository,
        LocaleInterface $locale,
        RepositoryInterface $giftCardChannelConfigurationRepository,
        GiftCardChannelConfiguration $giftCardChannelConfiguration,
        FactoryInterface $giftCardChannelConfigurationFactory
    ): void {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository->findOneBy(['code' => 'super_configuration'])->willReturn($configuration);
        $channelRepository->findOneByCode('super_channel')->willReturn($channel);
        $localeRepository->findOneBy(['code' => 'en_GB'])->willReturn($locale);

        $giftCardChannelConfigurationRepository->findOneBy([
            'configuration' => $configuration,
            'channel' => $channel,
            'locale' => $locale,
        ])->willReturn(null);

        $giftCardChannelConfigurationFactory->createNew()->willReturn($giftCardChannelConfiguration);
        $giftCardChannelConfiguration->setConfiguration($configuration)->shouldBeCalled();
        $giftCardChannelConfiguration->setChannel($channel)->shouldBeCalled();
        $giftCardChannelConfiguration->setLocale($locale)->shouldBeCalled();

        $configuration->addChannelConfiguration($giftCardChannelConfiguration)->shouldBeCalled();

        $this($command);
    }

    public function it_does_nothing_if_association_already_exists(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationInterface $configuration,
        ChannelRepositoryInterface $channelRepository,
        ChannelInterface $channel,
        RepositoryInterface $localeRepository,
        LocaleInterface $locale,
        RepositoryInterface $giftCardChannelConfigurationRepository,
        GiftCardChannelConfiguration $existingGiftCardChannelConfiguration,
        FactoryInterface $giftCardChannelConfigurationFactory
    ): void {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository->findOneBy(['code' => 'super_configuration'])->willReturn($configuration);
        $channelRepository->findOneByCode('super_channel')->willReturn($channel);
        $localeRepository->findOneBy(['code' => 'en_GB'])->willReturn($locale);

        $giftCardChannelConfigurationRepository->findOneBy([
            'configuration' => $configuration,
            'channel' => $channel,
            'locale' => $locale,
        ])->willReturn($existingGiftCardChannelConfiguration);

        $giftCardChannelConfigurationFactory->createNew()->shouldNotBeCalled();

        $this($command);
    }

    public function it_throws_exception_if_command_has_no_configuration_code(): void
    {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$command]);
    }

    public function it_throws_exception_if_configuration_is_not_found(
        RepositoryInterface $giftCardConfigurationRepository
    ): void {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository->findOneBy(['code' => 'super_configuration'])->willReturn(null);

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$command]);
    }

    public function it_throws_exception_if_channel_is_not_found(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationInterface $configuration,
        ChannelRepositoryInterface $channelRepository
    ): void {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository->findOneBy(['code' => 'super_configuration'])->willReturn($configuration);
        $channelRepository->findOneByCode('super_channel')->willReturn(null);

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$command]);
    }

    public function it_throws_exception_if_locale_is_not_found(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationInterface $configuration,
        ChannelRepositoryInterface $channelRepository,
        ChannelInterface $channel,
        RepositoryInterface $localeRepository
    ): void {
        $command = new AssociateConfigurationToChannel('en_GB', 'super_channel');
        $command->setConfigurationCode('super_configuration');

        $giftCardConfigurationRepository->findOneBy(['code' => 'super_configuration'])->willReturn($configuration);
        $channelRepository->findOneByCode('super_channel')->willReturn($channel);
        $localeRepository->findOneBy(['code' => 'en_GB'])->willReturn(null);

        $this->shouldThrow(InvalidArgumentException::class)
            ->during('__invoke', [$command]);
    }
}
