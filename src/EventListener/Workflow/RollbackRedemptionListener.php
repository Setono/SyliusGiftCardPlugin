<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener\Workflow;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Restores the gift card balance redeemed by an order when the order is cancelled
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class RollbackRedemptionListener
{
    public function __construct(private readonly GiftCardRedemptionMethodInterface $redemptionMethod)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->redemptionMethod->rollback($order);
    }
}
