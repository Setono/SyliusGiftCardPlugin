<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class GiftCardEligibilityChecker implements GiftCardEligibilityCheckerInterface
{
    public function __construct(
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
    ) {
    }

    public function getIneligibilityReason(GiftCardInterface $giftCard, ?OrderInterface $order = null): ?GiftCardIneligibilityReason
    {
        if (!$giftCard->isEnabled()) {
            return GiftCardIneligibilityReason::NotEnabled;
        }

        if ($giftCard->isExpired()) {
            return GiftCardIneligibilityReason::Expired;
        }

        if ($giftCard->getAmount() <= 0) {
            return GiftCardIneligibilityReason::NoBalance;
        }

        if (null === $order) {
            return null;
        }

        $orderChannel = $order->getChannel();
        if (null !== $orderChannel && $giftCard->getChannel()?->getCode() !== $orderChannel->getCode()) {
            return GiftCardIneligibilityReason::ChannelMismatch;
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null !== $orderCurrencyCode && $giftCard->getCurrencyCode() !== $orderCurrencyCode && !$this->isOffered($giftCard, $orderChannel)) {
            return GiftCardIneligibilityReason::CurrencyMismatch;
        }

        return null;
    }

    /**
     * A card held in another currency than the order's pays converted into the order's currency, as long as the
     * order's channel offers the card's currency, and with it the exchange rate the shop keeps for it (#488)
     */
    private function isOffered(GiftCardInterface $giftCard, ?ChannelInterface $channel): bool
    {
        return null !== $channel && in_array($giftCard->getCurrencyCode(), $this->currencyProvider->getCurrencyCodes($channel), true);
    }
}
