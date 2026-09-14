<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Reconciles the gift cards bought in an order once checkout completes
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class ReconcileGiftCardsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before Sylius' ApplyCreateTransitionOnOrderListener (400) cascades the order into existence and before
            // its ResolveOrderPaymentStateListener (200) can pay it on the spot, so every unit has its card before
            // anything can enable and email them. Mirrors the winzou priority -500
            'workflow.sylius_order_checkout.completed.complete' => ['__invoke', 500],
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardOperator->reconcile($order);
    }
}
