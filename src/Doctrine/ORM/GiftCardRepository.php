<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class GiftCardRepository extends EntityRepository implements GiftCardRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o');
    }

    public function findOneEnabledByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->findOneBy([
            'code' => $code,
            'channel' => $channel,
            'enabled' => true,
        ]);

        return $giftCard;
    }

    public function findOneByCode(string $code): ?GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->findOneBy([
            'code' => $code,
        ]);

        return $giftCard;
    }

    public function findOneByOrderItemUnit(OrderItemUnitInterface $orderItemUnit): ?GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->findOneBy([
            'orderItemUnit' => $orderItemUnit,
        ]);

        return $giftCard;
    }
}
