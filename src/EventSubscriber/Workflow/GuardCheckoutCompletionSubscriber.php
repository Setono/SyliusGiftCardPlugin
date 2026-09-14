<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Guard\GiftCardCoverageGuardInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Webmozart\Assert\Assert;

/**
 * Refuses to complete a checkout whose applied gift cards no longer pay what they did when they were applied
 *
 * This mirrors the equivalent winzou guard prepended in SetonoSyliusGiftCardExtension, so the plugin behaves the
 * same whichever state machine adapter the application is configured with. Only the adapter actually applying the
 * transition emits its events, so the two can never both run
 */
final class GuardCheckoutCompletionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GiftCardCoverageGuardInterface $guard)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Sylius registers no guard on complete, so there is nothing to order against. Mirrors the winzou priority 0
            'workflow.sylius_order_checkout.guard.complete' => ['__invoke', 0],
        ];
    }

    public function __invoke(GuardEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$this->guard->isSatisfiedBy($order)) {
            $event->setBlocked(true, 'The gift cards applied to the order no longer pay for it');
        }
    }
}
