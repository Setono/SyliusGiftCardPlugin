<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderGiftCardCodeOperatorInterface
{
    public function cancel(OrderInterface $order): void;
}
