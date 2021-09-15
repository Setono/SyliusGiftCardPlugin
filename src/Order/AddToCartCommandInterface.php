<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface as BaseCommandInterface;

interface AddToCartCommandInterface extends BaseCommandInterface
{
    public function getGiftCardInformation(): GiftCardInformationInterface;
}
