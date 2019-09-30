<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Application\Model;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface as SetonoSyliusGiftCardOrderInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderTrait as SetonoSyliusGiftCardOrderTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_order")
 */
class Order extends BaseOrder implements SetonoSyliusGiftCardOrderInterface
{
    use SetonoSyliusGiftCardOrderTrait {
        SetonoSyliusGiftCardOrderTrait::__construct as private __SetonoSyliusGiftCardOrderTraitConstruct;
    }

    public function __construct()
    {
        $this->__SetonoSyliusGiftCardOrderTraitConstruct();

        parent::__construct();
    }
}
