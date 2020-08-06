<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

interface CustomerChannelResolverInterface
{
    public function resolve(CustomerInterface $customer): ChannelInterface;
}
