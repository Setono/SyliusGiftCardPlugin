<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Order\Dto\AddGiftCardToCartInformationInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface as BaseCommandInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

interface AddToCartCommandInterface extends BaseCommandInterface
{
    public function getCart(): OrderInterface;

    public function getCartItem(): OrderItemInterface;

    public function getGiftCardInformation(): AddGiftCardToCartInformationInterface;
}
