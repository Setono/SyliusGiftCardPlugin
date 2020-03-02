<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Core\Model\ImageInterface;

interface GiftCardConfigurationImageInterface extends ImageInterface
{
    const TYPE_BACKGROUND = 'background';
}
