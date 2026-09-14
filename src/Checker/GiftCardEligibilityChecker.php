<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class GiftCardEligibilityChecker implements GiftCardEligibilityCheckerInterface
{
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
        if (null !== $orderCurrencyCode && $giftCard->getCurrencyCode() !== $orderCurrencyCode) {
            return GiftCardIneligibilityReason::CurrencyMismatch;
        }

        return null;
    }
}
