<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\CommandHandler;

use Setono\SyliusGiftCardPlugin\Api\Command\AssociateConfigurationToChannel;
use Setono\SyliusGiftCardPlugin\Model\GiftCardChannelConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Webmozart\Assert\Assert;

final class AssociateConfigurationToChannelHandler implements MessageHandlerInterface
{
    private RepositoryInterface $giftCardConfigurationRepository;

    private ChannelRepositoryInterface $channelRepository;

    private RepositoryInterface $localeRepository;

    private RepositoryInterface $giftCardChannelConfigurationRepository;

    private FactoryInterface $giftCardChannelConfigurationFactory;

    public function __construct(
        RepositoryInterface $giftCardConfigurationRepository,
        ChannelRepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository,
        RepositoryInterface $giftCardChannelConfigurationRepository,
        FactoryInterface $giftCardChannelConfigurationFactory
    ) {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->giftCardChannelConfigurationRepository = $giftCardChannelConfigurationRepository;
        $this->giftCardChannelConfigurationFactory = $giftCardChannelConfigurationFactory;
    }

    public function __invoke(AssociateConfigurationToChannel $command): GiftCardConfigurationInterface
    {
        Assert::notNull($command->getConfigurationCode());

        /** @var GiftCardConfigurationInterface|null $configuration */
        $configuration = $this->giftCardConfigurationRepository->findOneBy(['code' => $command->getConfigurationCode()]);
        Assert::notNull($configuration);

        $channel = $this->channelRepository->findOneByCode($command->channelCode);
        Assert::notNull($channel);

        /** @var LocaleInterface|null $locale */
        $locale = $this->localeRepository->findOneBy(['code' => $command->localeCode]);
        Assert::notNull($locale);

        /** @var GiftCardChannelConfigurationInterface|null $existingChannelConfiguration */
        $existingChannelConfiguration = $this->giftCardChannelConfigurationRepository->findOneBy([
            'configuration' => $configuration,
            'channel' => $channel,
            'locale' => $locale,
        ]);
        if (null !== $existingChannelConfiguration) {
            return $configuration;
        }

        /** @var GiftCardChannelConfigurationInterface $channelConfiguration */
        $channelConfiguration = $this->giftCardChannelConfigurationFactory->createNew();
        $channelConfiguration->setConfiguration($configuration);
        $channelConfiguration->setChannel($channel);
        $channelConfiguration->setLocale($locale);

        $configuration->addChannelConfiguration($channelConfiguration);

        return $configuration;
    }
}
