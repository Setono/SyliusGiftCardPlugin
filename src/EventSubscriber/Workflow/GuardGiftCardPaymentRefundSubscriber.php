<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\StateMachine\GiftCardPaymentRefundGuardInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Webmozart\Assert\Assert;

/**
 * Blocks refunding a gift card payment on its own while its order is not paid yet
 *
 * This mirrors the equivalent winzou guard callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class GuardGiftCardPaymentRefundSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GiftCardPaymentRefundGuardInterface $guard)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Sylius registers no guard on refund, so there is nothing to order against. Mirrors the winzou priority 0
            'workflow.sylius_payment.guard.refund' => ['__invoke', 0],
        ];
    }

    public function __invoke(GuardEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        if (!$this->guard->isSatisfiedBy($payment)) {
            $event->setBlocked(true, 'A gift card payment cannot be refunded on its own while its order is not paid; cancel the order instead');
        }
    }
}
