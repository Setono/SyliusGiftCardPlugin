<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Commits the gift card balance redeemed by an order when the order is placed
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class CommitRedemptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GiftCardRedemptionMethodInterface $redemptionMethod)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After Sylius' RequestOrderPaymentListener (700) has put the order in awaiting_payment and its
            // CreatePaymentListener (600) has created the gateway payments, so the gift card payments join a complete
            // set the payment state resolver can settle the order from. Mirrors the winzou priority -50
            'workflow.sylius_order.completed.create' => ['__invoke', 50],
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->redemptionMethod->commit($order);
    }
}
