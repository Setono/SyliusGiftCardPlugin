<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Order\Model\OrderInterface;

final class GiftCardCodeRepository extends EntityRepository implements GiftCardCodeRepositoryInterface
{
    public function findOneActiveByCodeAndChannelCode(string $code, string $channelCode): ?GiftCardCodeInterface
    {
        return $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->andWhere('o.channelCode = :channelCode')
            ->andWhere('o.isActive = true')
            ->setParameter('code', $code)
            ->setParameter('channelCode', $channelCode)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByCode(string $code): ?GiftCardCodeInterface
    {
        return $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllActiveByCurrentOrder(OrderInterface $order): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.currentOrder = :order')
            ->andWhere('o.isActive = true')
            ->setParameter('order', $order)
            ->getQuery()
            ->getResult()
        ;
    }
}
