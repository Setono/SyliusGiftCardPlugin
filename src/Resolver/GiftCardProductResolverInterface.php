<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\ProductInterface;

interface GiftCardProductResolverInterface
{
    public function isGiftCardProduct(ProductInterface $product): bool;
}
