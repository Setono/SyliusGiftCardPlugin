<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface GiftCardRepositoryInterface extends RepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder;

    public function findOneEnabledByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface;

    public function findOneByCode(string $code): ?GiftCardInterface;

    public function findOneByOrderItemUnit(OrderItemUnitInterface $orderItemUnit): ?GiftCardInterface;

    /**
     * @return GiftCardInterface[]
     */
    public function findEnabled(): array;

    public function createAccountListQueryBuilder(CustomerInterface $customer): QueryBuilder;
}
