<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<GiftCardDesignInterface>
 */
interface GiftCardDesignRepositoryInterface extends RepositoryInterface
{
    /**
     * @return list<GiftCardDesignInterface>
     */
    public function findEnabledByChannel(ChannelInterface $channel): array;

    public function countByChannel(ChannelInterface $channel): int;
}
