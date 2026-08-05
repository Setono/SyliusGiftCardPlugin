<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener\Workflow;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Reconciles the gift cards bought in an order once checkout completes
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class ReconcileGiftCardsListener
{
    public function __construct(private readonly OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardOperator->reconcile($order);
    }
}
