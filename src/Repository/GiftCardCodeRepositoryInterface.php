<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface GiftCardCodeRepositoryInterface extends RepositoryInterface
{
    public function findOneActiveByCodeAndChannelCode(string $code, string $channelCode): ?GiftCardCodeInterface;

    public function findOneByCode(string $code): ?GiftCardCodeInterface;

    public function findAllActiveByCurrentOrder(OrderInterface $order): array;
}
