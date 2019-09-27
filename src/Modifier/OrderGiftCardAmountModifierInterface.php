<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderGiftCardAmountModifierInterface
{
    public function increment(OrderInterface $order): void;

    public function decrement(OrderInterface $order): void;
}
