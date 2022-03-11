<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Setono\SyliusGiftCardPlugin\Exception\PromotionCouponNotFoundException;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;

final class PromotionApplicator implements GiftCardOrPromotionApplicatorInterface
{
    private PromotionCouponRepositoryInterface $promotionCouponRepository;

    public function __construct(PromotionCouponRepositoryInterface $promotionCouponRepository)
    {
        $this->promotionCouponRepository = $promotionCouponRepository;
    }

    public function apply(OrderInterface $order, string $giftCardOrPromotionCode): void
    {
        $promotionCoupon = $this->promotionCouponRepository->findOneBy(['code' => $giftCardOrPromotionCode]);
        if (null === $promotionCoupon) {
            throw new PromotionCouponNotFoundException($giftCardOrPromotionCode);
        }

        $order->setPromotionCoupon($promotionCoupon);
    }
}
