<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener\Workflow;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

final class EnableAndSendGiftCardListener
{
    public function __construct(private readonly OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardOperator->enable($order);
        $this->orderGiftCardOperator->send($order);
    }
}
