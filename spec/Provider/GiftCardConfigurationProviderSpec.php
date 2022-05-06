<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProvider;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardConfigurationProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardConfigurationProviderSpec extends ObjectBehavior
{
    public function let(
        GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory,
        LocaleContextInterface $localeContext,
        RepositoryInterface $localeRepository,
        ManagerRegistry $managerRegistry
    ) {
        $this->beConstructedWith($giftCardConfigurationRepository, $giftCardConfigurationFactory, $localeContext, $localeRepository, $managerRegistry);
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(GiftCardConfigurationProvider::class);
    }

    public function it_implements_gift_card_configuration_provider_interface(): void
    {
        $this->shouldImplement(GiftCardConfigurationProviderInterface::class);
    }

    public function it_provides_configuration_from_channel_and_locale(
        GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        ChannelInterface $channel,
        LocaleInterface $locale
    ): void {
        $configuration = new GiftCardConfiguration();
        $giftCardConfigurationRepository->findOneByChannelAndLocale($channel, $locale)->willReturn($configuration);

        $this->getConfiguration($channel, $locale)->shouldReturn($configuration);
    }

    public function it_provides_default_configuration_if_none_found(
        GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory,
        ChannelInterface $channel,
        LocaleInterface $locale,
        ManagerRegistry $managerRegistry,
        EntityManagerInterface $entityManager
    ) {
        $managerRegistry->getManagerForClass(GiftCardConfiguration::class)->willReturn($entityManager);
        $giftCardConfigurationRepository->findOneByChannelAndLocale($channel, $locale)->willReturn(null);

        $configuration = new GiftCardConfiguration();
        $configuration->addChannelConfiguration(new GiftCardChannelConfiguration());
        $giftCardConfigurationRepository->findDefault()->willReturn($configuration);

        $this->getConfiguration($channel, $locale)->shouldReturn($configuration);
    }
}
