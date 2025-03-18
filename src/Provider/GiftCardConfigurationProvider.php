<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\DoctrineObjectManagerTrait\ORM\ORMManagerTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardConfigurationProvider implements GiftCardConfigurationProviderInterface
{
    use ORMManagerTrait;

    public function __construct(
        private GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository,
        private GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory,
        private LocaleContextInterface $localeContext,
        private RepositoryInterface $localeRepository,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getConfiguration(BaseChannelInterface $channel, LocaleInterface $locale): GiftCardConfigurationInterface
    {
        $configuration = $this->giftCardConfigurationRepository->findOneByChannelAndLocale($channel, $locale);
        if ($configuration instanceof GiftCardConfigurationInterface) {
            return $configuration;
        }

        $configuration = $this->giftCardConfigurationRepository->findDefault();
        if ($configuration instanceof GiftCardConfigurationInterface) {
            return $configuration;
        }

        // todo add notification feature where the shop owner receives an email to update this configuration

        $configuration = $this->giftCardConfigurationFactory->createNew();
        $configuration->setCode('default');
        $configuration->setDefault(true);

        $manager = $this->getManager($configuration);
        $manager->persist($configuration);
        $manager->flush();

        return $configuration;
    }

    public function getConfigurationForGiftCard(GiftCardInterface $giftCard): GiftCardConfigurationInterface
    {
        $channel = $giftCard->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        try {
            $order = $giftCard->getOrder();

            $localeCode = $this->localeContext->getLocaleCode();
            if ($order instanceof OrderInterface) {
                $localeCode = $order->getLocaleCode();
            }

            $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
            if (!$locale instanceof LocaleInterface) {
                throw new LocaleNotFoundException();
            }
        } catch (LocaleNotFoundException) {
            $locale = $channel->getDefaultLocale();
        }

        Assert::notNull($locale);

        return $this->getConfiguration($channel, $locale);
    }
}
