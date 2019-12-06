<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\OrderCheckoutStates;

trait OrderRepositoryTrait
{
    /**
     * @param string $alias
     * @param string|null $indexBy The index for the from.
     *
     * @return QueryBuilder
     */
    abstract public function createQueryBuilder($alias, $indexBy = null);

    public function createQueryBuilderByGiftCard(string $giftCardId): QueryBuilder
    {
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
