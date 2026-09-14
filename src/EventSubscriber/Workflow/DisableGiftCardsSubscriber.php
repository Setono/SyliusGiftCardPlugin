<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Disables the gift cards bought in an order when the order is cancelled or its payment refunded in full
 *
 * Deliberately not on partially_refund: a partial refund does not say which of the order's payments, let alone
 * which of its items, the money went back for, so whether the cards were paid back is unknowable here. The
 * cards stay usable and the merchant disables them by hand where that is what the refund meant
 *
 * This mirrors the equivalent winzou callbacks prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class DisableGiftCardsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Right after RollbackRedemptionSubscriber (650): nothing Sylius does on cancel depends on it, so the
            // plugin's cancel work is done before Sylius' cascades start. Mirrors the winzou priority -640
            'workflow.sylius_order.completed.cancel' => ['__invoke', 640],
            // Sylius registers nothing on refund, so there is nothing to order against. Mirrors the winzou priority 0
            'workflow.sylius_order_payment.completed.refund' => ['__invoke', 0],
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardOperator->disable($order);
    }
}
