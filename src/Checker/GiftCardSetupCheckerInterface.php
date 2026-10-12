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
 * method is disabled. A merchant may disable it on purpose, to stop gift cards being redeemed for a while, but their
 * customers hold cards they cannot spend meanwhile, and may still be buying more, which is not something to forget
 * about. Either is only worth a warning while gift cards are at stake: while some enabled channel sells them, or while
 * customers hold usable ones with a balance, which a shop issuing its cards in the admin does without selling any
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
     * Whether the payment method gift card payments are made with does not exist while gift cards are at stake, so the
     * shop refuses every gift card a customer tries to pay with. A shop that neither sells gift cards nor has customers
     * holding usable ones has nothing to warn about
     */
    public function isPaymentMethodMissing(): bool;

    /**
     * The payment method gift card payments are made with, while it is disabled and gift cards are at stake, so the shop
     * refuses every gift card a customer tries to pay with; null when there is nothing to warn about
     */
    public function getDisabledPaymentMethod(): ?PaymentMethodInterface;
}
