<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\TestApplication\Repository;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\OrderRepositoryTrait as SetonoSyliusGiftCardPluginOrderRepositoryTrait;
use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface as SetonoSyliusGiftCardPluginOrderRepositoryInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

class OrderRepository extends BaseOrderRepository implements SetonoSyliusGiftCardPluginOrderRepositoryInterface
{
    use SetonoSyliusGiftCardPluginOrderRepositoryTrait;
}
