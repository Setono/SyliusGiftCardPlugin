<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<GiftCardDesignInterface>
 */
interface GiftCardDesignRepositoryInterface extends RepositoryInterface
{
    /**
     * The query behind the admin's design grid, with every design's translation in the given locale joined as
     * "translation", so the grid can sort by the name it shows
     */
    public function createListQueryBuilder(string $localeCode): QueryBuilder;

    /**
     * @return list<GiftCardDesignInterface>
     */
    public function findEnabledByChannel(ChannelInterface $channel): array;

    public function countByChannel(ChannelInterface $channel): int;
}
