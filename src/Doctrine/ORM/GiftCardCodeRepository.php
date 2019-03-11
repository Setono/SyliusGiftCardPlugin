<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Order\Model\OrderInterface;

final class GiftCardCodeRepository extends EntityRepository implements GiftCardCodeRepositoryInterface
{
    /**
     * @param string $code
     * @param string $channelCode
     *
     * @return GiftCardCodeInterface|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
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

    /**
     * @param string $code
     *
     * @return GiftCardCodeInterface|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
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
     * @param OrderInterface $order
     *
     * @return array
     */
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
