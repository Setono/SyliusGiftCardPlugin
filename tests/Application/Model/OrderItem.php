<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Application\Model;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\OrderItemTrait;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

/**
 * @method ProductInterface|null getProduct()
 */
#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    use OrderItemTrait;
}
