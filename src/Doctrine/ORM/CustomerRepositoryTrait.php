<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use function assert;
use Doctrine\ORM\EntityRepository;

/**
 * @mixin EntityRepository
 */
trait CustomerRepositoryTrait
{
    public function findByEmailPartForGiftCard(string $email, int $limit = 10): array
    {
        assert($this instanceof EntityRepository);

        return $this->createQueryBuilder('o')
            ->andWhere('o.email LIKE :phrase')
            ->setParameter('phrase', '%' . $email . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
