<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use function assert;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\OrderCheckoutStates;

/**
 * @mixin EntityRepository
 */
trait OrderRepositoryTrait
{
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
