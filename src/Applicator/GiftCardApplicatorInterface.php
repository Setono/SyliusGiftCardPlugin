<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface GiftCardApplicatorInterface extends GiftCardOrPromotionApplicatorInterface
{
    /**
     * @param string|GiftCardInterface|mixed $giftCard
     */
    public function remove(OrderInterface $order, $giftCard): void;
}
