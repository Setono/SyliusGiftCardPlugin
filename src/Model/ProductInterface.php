<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface as BaseProductInterface;

interface ProductInterface extends BaseProductInterface
{
    /**
     * True if this product is a gift card product
     */
    public function isGiftCard(): bool;

    public function setGiftCard(bool $giftCard): void;
}
