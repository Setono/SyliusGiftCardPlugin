<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Webmozart\Assert\Assert;

class GiftCardRepository extends EntityRepository implements GiftCardRepositoryInterface
{
    #[\Override]
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('customer')
            ->leftJoin('o.customer', 'customer')
        ;
    }

    #[\Override]
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

    #[\Override]
    public function findOneByCode(string $code): ?GiftCardInterface
    {
        $giftCard = $this->findOneBy([
            'code' => $code,
        ]);
        Assert::nullOrIsInstanceOf($giftCard, GiftCardInterface::class);

        return $giftCard;
    }

    #[\Override]
    public function findOneByOrderItemUnit(OrderItemUnitInterface $orderItemUnit): ?GiftCardInterface
    {
        $giftCard = $this->findOneBy([
            'orderItemUnit' => $orderItemUnit,
        ]);
        Assert::nullOrIsInstanceOf($giftCard, GiftCardInterface::class);

        return $giftCard;
    }

    #[\Override]
    public function findEnabled(): array
    {
        $giftCards = $this->findBy([
            'enabled' => true,
        ]);

        Assert::allIsInstanceOf($giftCards, GiftCardInterface::class);

        return $giftCards;
    }

    #[\Override]
    public function createAccountListQueryBuilder(CustomerInterface $customer): QueryBuilder
    {
        $qb = $this->createListQueryBuilder();
        $qb->andWhere('o.customer = :customer');
        $qb->setParameter('customer', $customer);

        return $qb;
    }
}
