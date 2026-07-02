<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Webmozart\Assert\Assert;

class GiftCardRepository extends EntityRepository implements GiftCardRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('customer')
            ->leftJoin('o.customer', 'customer')
            // hide pending (cart) gift cards from the admin list by default
            ->andWhere('o.orderItemUnit IS NULL OR o.enabled = true OR EXISTS (SELECT 1 FROM Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface t WHERE t.giftCard = o)')
        ;
    }

    public function findOneByCode(string $code): ?GiftCardInterface
    {
        $giftCard = $this->findOneBy(['code' => $code]);
        Assert::nullOrIsInstanceOf($giftCard, GiftCardInterface::class);

        return $giftCard;
    }

    public function findOneEnabledByCodeAndChannel(string $code, ChannelInterface $channel): ?GiftCardInterface
    {
        $giftCard = $this->findOneBy([
            'code' => $code,
            'channel' => $channel,
            'enabled' => true,
        ]);
        Assert::nullOrIsInstanceOf($giftCard, GiftCardInterface::class);

        return $giftCard;
    }

    public function findOneByOrderItemUnit(OrderItemUnitInterface $orderItemUnit): ?GiftCardInterface
    {
        $giftCard = $this->findOneBy(['orderItemUnit' => $orderItemUnit]);
        Assert::nullOrIsInstanceOf($giftCard, GiftCardInterface::class);

        return $giftCard;
    }

    public function findBalance(\DateTimeInterface $date): array
    {
        /** @var list<array{currencyCode: string, count: int|string, amount: int|string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.currencyCode AS currencyCode', 'COUNT(o.id) AS count', 'SUM(o.amount) AS amount')
            ->andWhere('o.enabled = true')
            ->andWhere('o.amount > 0')
            ->andWhere('o.expiresAt IS NULL OR o.expiresAt > :date')
            ->setParameter('date', $date)
            ->groupBy('o.currencyCode')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(
            static fn (array $row): array => [
                'currencyCode' => $row['currencyCode'],
                'count' => (int) $row['count'],
                'amount' => (int) $row['amount'],
            ],
            $rows,
        );
    }
}
