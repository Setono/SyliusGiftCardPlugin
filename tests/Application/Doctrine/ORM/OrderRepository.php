<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Application\Doctrine\ORM;

use Setono\SyliusGiftCardPlugin\Doctrine\ORM\OrderRepositoryInterface as SetonoSyliusGiftCardPluginOrderRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\OrderRepositoryTrait as SetonoSyliusGiftCardPluginOrderRepositoryTrait;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

class OrderRepository extends BaseOrderRepository implements SetonoSyliusGiftCardPluginOrderRepositoryInterface
{
    use SetonoSyliusGiftCardPluginOrderRepositoryTrait;
}
