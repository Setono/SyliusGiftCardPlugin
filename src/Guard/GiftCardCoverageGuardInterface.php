<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Guard;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;

/**
 * Nothing re-validates a gift card between the moment it is applied to a cart and the moment the order is placed,
 * while the payment step is skipped (or the gateway payment sized) on the strength of it. This guard is what the
 * checkout's complete transition asks, on either state machine adapter, before it lets an order through
 */
interface GiftCardCoverageGuardInterface
{
    /**
     * Whether checkout may complete as far as the applied gift cards are concerned: every one of them can still pay
     * for the order and, together with the order's other payments, they add up to its total. An order without gift
     * cards is none of this guard's business and passes
     */
    public function isSatisfiedBy(CoreOrderInterface $order): bool;

    /**
     * The applied gift cards that can no longer pay for the order
     *
     * @return list<GiftCardInterface>
     */
    public function getInapplicableGiftCards(OrderInterface $order): array;

    /**
     * Whether what the gift cards cover and what the order's other payments collect add up to the order total
     */
    public function isTotalCovered(OrderInterface $order): bool;
}
