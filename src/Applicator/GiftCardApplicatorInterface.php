<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Setono\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardCurrencyMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

interface GiftCardApplicatorInterface
{
    /**
     * Applies $giftCard to $order, guarding that it is usable and compatible with the order, then delegating to the
     * configured redemption method
     *
     * @param string|GiftCardInterface $giftCard a gift card or its (possibly formatted) code
     *
     * @throws GiftCardNotFoundException if the gift card code is not found
     * @throws ChannelMismatchException if the order channel does not match the gift card channel
     * @throws GiftCardCurrencyMismatchException if the order currency does not match the gift card currency
     */
    public function apply(OrderInterface $order, $giftCard): void;

    /**
     * @param string|GiftCardInterface $giftCard a gift card or its (possibly formatted) code
     *
     * @throws GiftCardNotFoundException if the gift card code is not found
     */
    public function remove(OrderInterface $order, $giftCard): void;
}
