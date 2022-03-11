<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Doctrine\Persistence\ObjectManager;
use Laminas\Stdlib\PriorityQueue;
use Setono\SyliusGiftCardPlugin\Exception\ExceptionInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class GiftCardOrPromotionApplicator implements GiftCardOrPromotionApplicatorInterface
{
    private PriorityQueue $applicators;

    private OrderProcessorInterface $orderProcessor;

    private ObjectManager $orderManager;

    public function __construct(OrderProcessorInterface $orderProcessor, ObjectManager $orderManager)
    {
        $this->applicators = new PriorityQueue();

        $this->orderProcessor = $orderProcessor;
        $this->orderManager = $orderManager;
    }

    public function addApplicator(GiftCardOrPromotionApplicatorInterface $applicator, int $priority = 0): void
    {
        $this->applicators->insert($applicator, $priority);
    }

    public function apply(OrderInterface $order, string $giftCardOrPromotionCode): void
    {
        foreach ($this->applicators->toArray() as $applicator) {
            try {
                $applicator->apply($order, $giftCardOrPromotionCode);

                break;
            } catch (ExceptionInterface $e) {
            }
        }

        $this->orderProcessor->process($order);

        $this->orderManager->flush();
    }
}
