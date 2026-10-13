<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Payment\VirtualGiftCardOrderPaymentCapturerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Completes the authorized payment of an order that buys only virtual gift cards, when the application asks for it
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class CaptureVirtualGiftCardOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly VirtualGiftCardOrderPaymentCapturerInterface $capturer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After ResolveOrderPaymentStateListener (100) has authorized the order, so completing the payment takes it
            // on to paid. Mirrors the winzou priority -50
            'workflow.sylius_payment.completed.authorize' => ['__invoke', 50],
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->capturer->capture($payment);
    }
}
