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

    public function createQueryBuilderByGiftCardCodeId(string $giftCardCodeId): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.payedByGiftCardCodes', 'giftCardCode')
            ->where('giftCardCode.id = :giftCardCodeId')
            ->setParameter('giftCardCodeId', $giftCardCodeId)
            ;
    }
}
