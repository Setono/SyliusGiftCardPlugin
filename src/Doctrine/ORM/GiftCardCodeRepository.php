<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Order\Model\OrderInterface;

final class GiftCardCodeRepository extends EntityRepository implements GiftCardCodeRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
//            ->join('o.channel', 'channel')
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneActiveByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardCodeInterface
    {
        return $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->andWhere('o.channel = :channel')
            ->andWhere('o.active = true')
            ->setParameter('code', $code)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByCode(string $code): ?GiftCardCodeInterface
    {
        return $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function findActiveByCurrentOrder(OrderInterface $order): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.currentOrder = :order')
            ->andWhere('o.active = true')
            ->setParameter('order', $order)
            ->getQuery()
            ->getResult()
        ;
    }
}
