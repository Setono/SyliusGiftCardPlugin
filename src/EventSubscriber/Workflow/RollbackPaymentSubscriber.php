<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Gives a gift card back what a payment took when that payment is refunded
 *
 * This runs for every refunded payment and the redemption method ignores those that are not gift card payments.
 * It is also what restores the balance when an order is cancelled, since rollback() refunds the gift card payments
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class RollbackPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GiftCardRedemptionMethodInterface $redemptionMethod)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before Sylius' ResolveOrderPaymentStateListener (100) works out the order's payment state from the
            // refunded payment, so the card is whole again before anything reacts to the order being (partially)
            // refunded. Mirrors the winzou priority -150
            'workflow.sylius_payment.completed.refund' => ['__invoke', 150],
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->redemptionMethod->rollbackPayment($payment);
    }
}
