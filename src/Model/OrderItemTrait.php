<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

trait OrderItemTrait
{
    public function equals(BaseOrderItemInterface $item): bool
    {
        return parent::equals($item) && !$this->getProduct()->isGiftCard();
    }
}
