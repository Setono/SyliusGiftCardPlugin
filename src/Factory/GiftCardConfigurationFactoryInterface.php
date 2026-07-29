<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface GiftCardConfigurationFactoryInterface extends FactoryInterface
{
    #[\Override]
    public function createNew(): GiftCardConfigurationInterface;
}
