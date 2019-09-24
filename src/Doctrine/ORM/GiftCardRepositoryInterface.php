<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Doctrine\ORM;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface GiftCardRepositoryInterface extends RepositoryInterface
{
    public function findOneByProduct(ProductInterface $product): ?GiftCardInterface;
}
