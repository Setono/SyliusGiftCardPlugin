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
    public function createListQueryBuilder(): QueryBuilder;

    public function findOneActiveByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardCodeInterface;

    public function findOneByCode(string $code): ?GiftCardCodeInterface;

    public function findActiveByCurrentOrder(OrderInterface $order): array;
}
