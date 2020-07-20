<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

trait CustomerRepositoryTrait
{
    /**
     * @param string $alias
     * @param string|null $indexBy The index for the from.
     *
     * @return QueryBuilder
     */
    abstract public function createQueryBuilder($alias, $indexBy = null);

    public function findByEmailPart(string $email, int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.email LIKE :phrase')
            ->setParameter('phrase', '%' . $email . '%')
            ->setMaxResult($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
