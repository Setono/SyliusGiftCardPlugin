<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\NonUniqueResultException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;

final class GiftCardRepository extends EntityRepository implements GiftCardRepositoryInterface
{
    /**
     * @throws NonUniqueResultException
     */
    public function findOneByProduct(ProductInterface $product): ?GiftCardInterface
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
