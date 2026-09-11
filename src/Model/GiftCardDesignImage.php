<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\Image;

class GiftCardDesignImage extends Image implements GiftCardDesignImageInterface
{
    public function __construct()
    {
        $this->type = self::TYPE_FRONT;
    }
}
