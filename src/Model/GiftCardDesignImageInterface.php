<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ImageInterface;

interface GiftCardDesignImageInterface extends ImageInterface
{
    public const TYPE_FRONT = 'front';

    public const TYPE_BACK = 'back';
}
