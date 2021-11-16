<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart as BaseAddItemToCart;

class AddItemToCart extends BaseAddItemToCart
{
    protected ?int $amount;

    protected ?string $customMessage;

    public function __construct(string $productCode, string $productVariantCode, int $quantity, int $amount = null, string $customMessage = null)
    {
        parent::__construct($productCode, $productVariantCode, $quantity);

        $this->amount = $amount;
        $this->customMessage = $customMessage;
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
