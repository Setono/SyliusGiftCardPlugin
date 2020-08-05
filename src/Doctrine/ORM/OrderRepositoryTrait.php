<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use function assert;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Customer\Model\CustomerInterface;

/**
 * @mixin EntityRepository
 */
trait OrderRepositoryTrait
{
    public function findLatestByCustomer(CustomerInterface $customer): ?OrderInterface
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->addOrderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function createQueryBuilderByGiftCard(string $giftCardId): QueryBuilder
    {
        assert($this instanceof EntityRepository);

        return $this->createQueryBuilder('o')
            ->join('o.giftCards', 'g')
            ->andWhere('g.id = :id')
            ->setParameter('id', $giftCardId)
        ;
    }

    public function createCompletedQueryBuilderByGiftCard(string $giftCardId): QueryBuilder
    {
        return $this->createQueryBuilderByGiftCard($giftCardId)
            ->andWhere('o.checkoutState = :checkoutState')
            ->setParameter('checkoutState', OrderCheckoutStates::STATE_COMPLETED)
            ;
    }
}
