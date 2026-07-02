<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

enum GiftCardDeliveryType: string
{
    case Virtual = 'virtual';
    case Physical = 'physical';
}
