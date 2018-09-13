<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Assigner;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderGiftCardCodeAssignerInterface
{
    public function assignGiftCardCode(OrderInterface $order): void;
}
