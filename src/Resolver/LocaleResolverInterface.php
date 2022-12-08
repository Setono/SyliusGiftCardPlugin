<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface LocaleResolverInterface
{
    public function resolveFromCustomer(CustomerInterface $customer): string;

    public function resolveFromOrder(OrderInterface $order): string;

    public function resolveFromChannel(ChannelInterface $channel): string;
}
