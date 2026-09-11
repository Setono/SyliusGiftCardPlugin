<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<GiftCardInterface>
 */
interface GiftCardRepositoryInterface extends RepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder;

    public function findOneByCode(string $code): ?GiftCardInterface;

    public function findOneEnabledByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface;

    public function findOneByOrderItemUnit(OrderItemUnitInterface $orderItemUnit): ?GiftCardInterface;

    /**
     * Aggregates the outstanding balance of all usable gift cards, grouped by currency, computed in SQL.
     *
     * Expiry is judged against $date, which defaults to now — the usual question is what is outstanding
     * today, so callers only pass a date when they want a different point in time.
     *
     * @return list<array{currencyCode: string, count: int, amount: int}>
     */
    public function findBalance(?\DateTimeInterface $date = null): array;
}
