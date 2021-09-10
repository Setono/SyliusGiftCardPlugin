<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface as BaseProductInterface;

interface ProductInterface extends BaseProductInterface
{
    /**
     * True if product is a gift card
     */
    public function isGiftCard(): bool;

    public function setGiftCard(bool $isGiftCard): void;

    /**
     * True if product is a gift card with configurable amount
     */
    public function isGiftCardAmountConfigurable(): bool;

    public function setGiftCardAmountConfigurable(bool $giftCardAmountConfigurable): void;
}
