<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Checker;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class GiftCardApplicabilityChecker implements GiftCardApplicabilityCheckerInterface
{
    public function getInapplicabilityReason(GiftCardInterface $giftCard, ?OrderInterface $order = null): ?GiftCardInapplicabilityReason
    {
        if (!$giftCard->isEnabled()) {
            return GiftCardInapplicabilityReason::NotEnabled;
        }

        if ($giftCard->isExpired()) {
            return GiftCardInapplicabilityReason::Expired;
        }

        if ($giftCard->getAmount() <= 0) {
            return GiftCardInapplicabilityReason::NoBalance;
        }

        if (null === $order) {
            return null;
        }

        $orderChannel = $order->getChannel();
        if (null !== $orderChannel && $giftCard->getChannel()?->getCode() !== $orderChannel->getCode()) {
            return GiftCardInapplicabilityReason::ChannelMismatch;
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null !== $orderCurrencyCode && $giftCard->getCurrencyCode() !== $orderCurrencyCode) {
            return GiftCardInapplicabilityReason::CurrencyMismatch;
        }

        return null;
    }
}
