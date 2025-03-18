<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart as BaseAddItemToCart;

class AddItemToCart extends BaseAddItemToCart
{
    public function __construct(string $orderTokenValue, string $productVariantCode, int $quantity, protected ?int $amount = null, protected ?string $customMessage = null)
    {
        parent::__construct($orderTokenValue, $productVariantCode, $quantity);
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }
}
