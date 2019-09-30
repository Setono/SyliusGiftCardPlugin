<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Modifier;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface OrderGiftCardAmountModifierInterface
{
    public function increment(OrderInterface $order): void;

    public function decrement(OrderInterface $order): void;
}
