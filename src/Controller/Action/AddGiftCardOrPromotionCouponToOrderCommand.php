<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\PromotionCouponInterface;

final class AddGiftCardOrPromotionCouponToOrderCommand
{
    /** @var GiftCardInterface|null */
    private $giftCard;

    /** @var PromotionCouponInterface|null */
    private $promotionCoupon;

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    public function getPromotionCoupon(): ?PromotionCouponInterface
    {
        return $this->promotionCoupon;
    }

    public function setPromotionCoupon(?PromotionCouponInterface $promotionCoupon): void
    {
        $this->promotionCoupon = $promotionCoupon;
    }
}
