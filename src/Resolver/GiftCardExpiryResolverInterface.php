<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

interface GiftCardExpiryResolverInterface
{
    /**
     * Returns when a gift card issued right now expires: the configured default validity period from now, through the
     * end of that day. Returns null when gift cards are configured to never expire.
     *
     * For a gift card bought in the shop, the validity counts from the purchase, i.e. when the order is placed. Add to
     * cart gives the card a provisional expiry, and checkout completion (OrderGiftCardOperatorInterface::reconcile())
     * replaces it, so the time a card spends in the cart does not count against it and every card bought on one order
     * expires at the same moment. It does not count from payment, even when payment follows days later (a bank
     * transfer, say): the rules a merchant sets the period to meet usually count from the purchase. A gift card issued
     * in the admin counts from its creation, and the admin can change the date on the form.
     *
     * Decorate this service to compute the expiry differently
     */
    public function resolve(): ?\DateTimeImmutable;
}
