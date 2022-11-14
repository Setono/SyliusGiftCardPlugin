<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Webmozart\Assert\Assert;

class GiftCardConfigurationRepository extends EntityRepository implements GiftCardConfigurationRepositoryInterface
{
    public function findOneByChannelAndLocale(ChannelInterface $channel, LocaleInterface $locale): ?GiftCardConfigurationInterface
    {
        $obj = $this->createQueryBuilder('o')
            ->join('o.channelConfigurations', 'c')
            ->andWhere('c.channel = :channel')
            ->andWhere('c.locale = :locale')
            ->setParameters([
                'channel' => $channel,
                'locale' => $locale,
            ])
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, GiftCardConfigurationInterface::class);

        return $obj;
    }

    public function findDefault(): ?GiftCardConfigurationInterface
    {
        $obj = $this->findOneBy([
            'default' => true,
        ]);

        Assert::nullOrIsInstanceOf($obj, GiftCardConfigurationInterface::class);

        return $obj;
    }
}
