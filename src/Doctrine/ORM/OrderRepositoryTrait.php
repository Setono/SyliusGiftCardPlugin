<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

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
            ->where('g.id = :id')
            ->setParameter('id', $giftCardId)
        ;
    }
}
