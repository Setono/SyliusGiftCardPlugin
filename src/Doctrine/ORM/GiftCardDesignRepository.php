<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Webmozart\Assert\Assert;

class GiftCardDesignRepository extends EntityRepository implements GiftCardDesignRepositoryInterface
{
    public function createListQueryBuilder(string $localeCode): QueryBuilder
    {
        // Joined to sort by, not fetched: a design without a translation in this locale shows its fallback one, which
        // a fetch join limited to this locale would keep out of the translations it loads
        return $this->createQueryBuilder('o')
            ->leftJoin('o.translations', 'translation', Join::WITH, 'translation.locale = :localeCode')
            ->setParameter('localeCode', $localeCode)
        ;
    }

    public function findEnabledByChannel(ChannelInterface $channel): array
    {
        /** @var list<GiftCardDesignInterface> $designs */
        $designs = $this->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('o.enabled = true')
            ->andWhere('channel = :channel')
            ->setParameter('channel', $channel)
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        Assert::allIsInstanceOf($designs, GiftCardDesignInterface::class);

        return $designs;
    }

    public function countByChannel(ChannelInterface $channel): int
    {
        /** @var int $count */
        $count = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count;
    }
}
