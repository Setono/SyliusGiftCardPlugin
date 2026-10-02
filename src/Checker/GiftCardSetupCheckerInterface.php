<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The default design is not created on the fly (see GiftCardDesignProvider), so a shop can sell gift cards in a
 * channel that has no design to print them with. The product page copes, the picker is simply not rendered, but
 * the merchant should know, and this is what the admin asks to find out.
 *
 * Neither is the payment method gift card payments are made with (see GiftCardPaymentMethodProviderInterface). Without
 * it the shop refuses every gift card, which the merchant should know about even sooner
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
}
