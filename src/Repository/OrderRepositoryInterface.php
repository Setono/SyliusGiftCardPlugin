<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface as BaseOrderRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerInterface;

interface OrderRepositoryInterface extends BaseOrderRepositoryInterface
{
    public function findLatestByCustomer(CustomerInterface $customer): ?OrderInterface;

    public function createQueryBuilderByGiftCard(string $giftCardId): QueryBuilder;

    public function createCompletedQueryBuilderByGiftCard(string $giftCardId): QueryBuilder;
}
