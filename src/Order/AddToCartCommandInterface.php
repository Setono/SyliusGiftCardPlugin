<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface as BaseCommandInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

interface AddToCartCommandInterface extends BaseCommandInterface
{
    #[\Override]
    public function getCart(): OrderInterface;

    #[\Override]
    public function getCartItem(): OrderItemInterface;

    public function getGiftCardInformation(): GiftCardInformationInterface;
}
