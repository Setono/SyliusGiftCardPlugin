<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\PromotionCouponInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Promotion\Action\PromotionApplicatorInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class CouponCodeApplicator implements CouponCodeApplicatorInterface
{
    /** @var PromotionCouponRepositoryInterface */
    private $promotionCouponRepository;

    /** @var OrderProcessorInterface */
    private $orderProcessor;

    /** @var EntityManagerInterface */
    private $orderManager;

    /** @var PromotionApplicatorInterface */
    private $promotionApplicator;

    public function __construct(
        PromotionCouponRepositoryInterface $promotionCouponRepository,
        OrderProcessorInterface $orderProcessor,
        EntityManagerInterface $orderManager,
        PromotionApplicatorInterface $promotionApplicator
    ) {
        $this->promotionCouponRepository = $promotionCouponRepository;
        $this->orderProcessor = $orderProcessor;
        $this->orderManager = $orderManager;
        $this->promotionApplicator = $promotionApplicator;
    }

    public function apply(OrderInterface $order, string $couponCode): void
    {
        $promotionCoupon = $this->promotionCouponRepository->findOneBy(['code' => $couponCode]);
        if ($promotionCoupon instanceof PromotionCouponInterface) {
            $order->setPromotionCoupon($promotionCoupon);
            $promotion = $promotionCoupon->getPromotion();
            Assert::notNull($promotion);
            $this->promotionApplicator->apply($order, $promotion);
            $this->orderProcessor->process($order);

            $this->orderManager->flush();
        } else {
            throw new NotFoundHttpException('Impossible to find the coupon');
        }
    }
}
