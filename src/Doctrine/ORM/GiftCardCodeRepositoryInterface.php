<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface GiftCardCodeRepositoryInterface extends RepositoryInterface
{
    /**
     * @return QueryBuilder
     */
    public function createListQueryBuilder(): QueryBuilder;

    /**
     * @param string $code
     * @param ChannelInterface $channel
     *
     * @return GiftCardCodeInterface|null
     */
    public function findOneActiveByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardCodeInterface;

    /**
     * @param string $code
     *
     * @return GiftCardCodeInterface|null
     */
    public function findOneByCode(string $code): ?GiftCardCodeInterface;

    /**
     * @param OrderInterface $order
     *
     * @return array
     */
    public function findActiveByCurrentOrder(OrderInterface $order): array;
}
