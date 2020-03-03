<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfigurationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardChannelConfigurationProvider implements GiftCardChannelConfigurationProviderInterface
{
    /** @var RepositoryInterface */
    private $configurationRepository;

    public function getChannelConfiguration(ChannelInterface $channel, LocaleInterface $locale): ?GiftCardChannelConfigurationInterface
    {
        $configuration = $this->configurationRepository->findOneBy(['channel' => $channel, 'locale' => $locale]);
        if (!$configuration instanceof GiftCardChannelConfigurationInterface) {
            $configuration = $this->configurationRepository->findOneBy(['default' => true]);
        }

        return $configuration;
    }
}
