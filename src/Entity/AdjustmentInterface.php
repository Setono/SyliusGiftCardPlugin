<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Entity;

use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;

interface AdjustmentInterface extends BaseAdjustmentInterface
{
    public const ORDER_GIFT_CARD_ADJUSTMENT = 'order_gift_card';
}
