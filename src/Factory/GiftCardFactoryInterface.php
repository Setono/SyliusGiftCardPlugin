<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface GiftCardFactoryInterface extends FactoryInterface
{
    public function createWithProduct(ProductInterface $product): GiftCardInterface;
}
