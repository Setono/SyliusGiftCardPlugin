<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface GiftCardOrPromotionApplicatorInterface
{
    public function apply(OrderInterface $order, string $giftCardOrPromotionCode): void;
}
