<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardChannelConfigurationProvider implements GiftCardChannelConfigurationProviderInterface
{
    /** @var RepositoryInterface */
    private $configurationRepository;

    /** @var LocaleContextInterface */
    private $localeContext;

    /** @var RepositoryInterface */
    private $localeRepository;

    public function __construct(
        RepositoryInterface $configurationRepository,
        LocaleContextInterface $localeContext,
        RepositoryInterface $localeRepository
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->localeContext = $localeContext;
        $this->localeRepository = $localeRepository;
    }

    public function getConfiguration(ChannelInterface $channel, LocaleInterface $locale): ?GiftCardConfigurationInterface
    {
        $channelConfiguration = $this->configurationRepository->findOneBy(['channel' => $channel, 'locale' => $locale]);
        if (!$channelConfiguration instanceof GiftCardChannelConfigurationInterface) {
            $channelConfiguration = $this->configurationRepository->findOneBy(['default' => true]);
        }

        if ($channelConfiguration instanceof GiftCardChannelConfigurationInterface) {
            return $channelConfiguration->getConfiguration();
        }

        return null;
    }

    public function getConfigurationForGiftCard(GiftCardInterface $giftCard): ?GiftCardConfigurationInterface
    {
        $channel = $giftCard->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        try {
            $order = $giftCard->getOrder();
            if ($order instanceof OrderInterface) {
                $localeCode = $order->getLocaleCode();
            } else {
                $localeCode = $this->localeContext->getLocaleCode();
            }
            $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
            if (!$locale instanceof LocaleInterface) {
                throw new LocaleNotFoundException();
            }
        } catch (LocaleNotFoundException $exception) {
            $locale = $channel->getDefaultLocale();
        }

        return $this->getConfiguration($channel, $locale);
    }
}
