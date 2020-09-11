<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Application\Repository;

use Setono\SyliusCatalogPromotionPlugin\Doctrine\ORM\ProductRepositoryTrait as CatalogPromotionProductRepositoryTrait;
use Setono\SyliusCatalogPromotionPlugin\Repository\ProductRepositoryInterface as CatalogPromotionProductRepositoryInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;

class ProductRepository extends BaseProductRepository implements CatalogPromotionProductRepositoryInterface
{
    use CatalogPromotionProductRepositoryTrait;
}
