<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener\Workflow;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Modifier\OrderGiftCardAmountModifierInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

final class DecrementGiftCardAmountListener
{
    public function __construct(private readonly OrderGiftCardAmountModifierInterface $orderGiftCardAmountModifier)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardAmountModifier->decrement($order);
    }
}
