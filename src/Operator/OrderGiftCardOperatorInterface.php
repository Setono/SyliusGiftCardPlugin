<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderGiftCardOperatorInterface
{
    /**
     * Will create and persist all gift cards on the given order
     */
    public function associateToCustomer(OrderInterface $order): void;

    public function enable(OrderInterface $order): void;

    public function disable(OrderInterface $order): void;
}
