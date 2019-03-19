<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Repository\OrderRepositoryInterface as BaseOrderRepositoryInterface;

interface OrderRepositoryInterface extends BaseOrderRepositoryInterface
{
    /**
     * @param string $giftCardCodeId
     *
     * @return QueryBuilder
     */
    public function createQueryBuilderByGiftCardCodeId(string $giftCardCodeId): QueryBuilder;
}
