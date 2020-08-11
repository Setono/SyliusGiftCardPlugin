<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Sylius\Component\Core\Model\PromotionCouponInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Webmozart\Assert\Assert;

class PromotionCouponToCodeDataTransformer implements DataTransformerInterface
{
    /** @var PromotionCouponRepositoryInterface */
    private $promotionCouponRepository;

    public function __construct(PromotionCouponRepositoryInterface $promotionCouponRepository)
    {
        $this->promotionCouponRepository = $promotionCouponRepository;
    }

    /**
     * @param PromotionCouponInterface|mixed $value
     */
    public function transform($value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        Assert::isInstanceOf($value, PromotionCouponInterface::class);

        return $value->getCode();
    }

    public function reverseTransform($value): ?PromotionCouponInterface
    {
        if (null === $value || '' === $value) {
            return null;
        }

        /** @var PromotionCouponInterface|null $promotionCoupon */
        $promotionCoupon = $this->promotionCouponRepository->findOneBy(['code' => $value]);

        return $promotionCoupon;
    }
}
