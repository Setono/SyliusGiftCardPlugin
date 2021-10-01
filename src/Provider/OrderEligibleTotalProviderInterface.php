<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface OrderEligibleTotalProviderInterface
{
    public function getEligibleTotal(OrderInterface $order, GiftCardInterface $giftCard): int;
}
