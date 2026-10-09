<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * The default design is not created on the fly (see GiftCardDesignProvider), so a shop can sell gift cards in a
 * channel that has no design to print them with. The product page copes, the picker is simply not rendered, but
 * the merchant should know, and this is what the admin asks to find out.
 *
 * Neither is the payment method gift card payments are made with (see GiftCardPaymentMethodProviderInterface). Without
 * it the shop refuses every gift card, which the merchant should know about even sooner, and so it does while the
 * method is disabled. A merchant may disable it on purpose, to stop gift cards being redeemed for a while, but the shop
 * goes on selling cards its customers cannot spend, which is not something to forget about
 */
interface GiftCardSetupCheckerInterface
{
    /**
     * The enabled channels that have an enabled gift card product but no enabled gift card design. A channel that
     * does not sell gift cards has nothing to warn about
     *
     * @return list<ChannelInterface>
     */
    public function getChannelsWithoutDesign(): array;

    /**
     * Whether some enabled channel sells gift cards while the payment method gift card payments are made with does not
     * exist, so the shop refuses every gift card a customer tries to pay with. A shop that does not sell gift cards
     * has nothing to warn about
     */
    public function isPaymentMethodMissing(): bool;

    /**
     * The payment method gift card payments are made with, while it is disabled and some enabled channel sells gift
     * cards, so the shop refuses every gift card a customer tries to pay with; null when there is nothing to warn about
     */
    public function getDisabledPaymentMethod(): ?PaymentMethodInterface;
}
