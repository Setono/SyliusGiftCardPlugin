<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Sylius keeps every order in its channel's base currency, and the channel's other currencies only change how amounts
 * are displayed, converted with the exchange rates the shop keeps. A gift card is held in the currency it was bought or
 * issued in, which may be any of the channel's currencies: a card bought for DKK 300 while browsing in DKK holds DKK 300,
 * and is converted into the order's currency when it pays (#488).
 *
 * Decorate this service to narrow the currencies, to the base currency alone say, which holds every card in the
 * currency its orders are kept in
 */
interface GiftCardCurrencyProviderInterface
{
    /**
     * The currencies a gift card of the channel may be held in: the channel's base currency first, then the other
     * currencies the channel offers
     *
     * @return list<string>
     */
    public function getCurrencyCodes(ChannelInterface $channel): array;

    /**
     * The currency a gift card bought in the channel now is held in: the currency the customer is browsing in, when the
     * channel offers it, else the channel's base currency
     */
    public function getCurrencyCode(ChannelInterface $channel): string;
}
