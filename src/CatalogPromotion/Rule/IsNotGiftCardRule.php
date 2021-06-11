<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\CatalogPromotion\Rule;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCatalogPromotionPlugin\Rule\Rule;
use function sprintf;

final class IsNotGiftCardRule extends Rule
{
    public const TYPE = 'has_no_gift_card';

    public function filter(QueryBuilder $queryBuilder, array $configuration): void
    {
        $rootAlias = $this->getRootAlias($queryBuilder);
        $productAlias = self::generateAlias('product');

        $queryBuilder
            ->join(sprintf('%s.product', $rootAlias), $productAlias)
            ->andWhere(sprintf('%s.giftCard = false', $productAlias))
        ;
    }
}
