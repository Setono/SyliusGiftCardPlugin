<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProvider;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardChannelConfigurationProviderSpec extends ObjectBehavior
{
    public function let(
        RepositoryInterface $configurationRepository,
        RepositoryInterface $defaultConfigurationRepository,
        LocaleContextInterface $localeContext,
        RepositoryInterface $localeRepository
    ) {
        $this->beConstructedWith($configurationRepository, $defaultConfigurationRepository, $localeContext, $localeRepository);
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(GiftCardChannelConfigurationProvider::class);
    }

    public function it_implements_gift_card_channel_configuration_provider_interface(): void
    {
        $this->shouldImplement(GiftCardChannelConfigurationProviderInterface::class);
    }

    public function it_provides_configuration_from_channel_and_locale(
        RepositoryInterface $configurationRepository,
        ChannelInterface $channel,
        LocaleInterface $locale
    ): void {
        $configuration = new GiftCardConfiguration();
        $channelConfiguration = new GiftCardChannelConfiguration();
        $channelConfiguration->setConfiguration($configuration);
        $configurationRepository->findOneBy(['channel' => $channel, 'locale' => $locale])->willReturn($channelConfiguration);

        $this->getConfiguration($channel, $locale)->shouldReturn($configuration);
    }

    public function it_provides_default_configuration_if_none_found(
        RepositoryInterface $configurationRepository,
        RepositoryInterface $defaultConfigurationRepository,
        ChannelInterface $channel,
        LocaleInterface $locale
    ) {
        $configurationRepository->findOneBy(['channel' => $channel, 'locale' => $locale])->willReturn(null);

        $defaultConfiguration = new GiftCardConfiguration();
        $defaultChannelConfiguration = new GiftCardChannelConfiguration();
        $defaultChannelConfiguration->setConfiguration($defaultConfiguration);
        $defaultConfigurationRepository->findOneBy(['default' => true])->willReturn($defaultConfiguration);

        $this->getConfiguration($channel, $locale)->shouldReturn($defaultConfiguration);
    }
}
