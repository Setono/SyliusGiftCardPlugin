<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

class RemoveGiftCardFromOrder implements GiftCardCodeAwareInterface
{
    public ?string $giftCardCode = null;

    public string $orderTokenValue;

    public function __construct(string $orderTokenValue)
    {
        $this->orderTokenValue = $orderTokenValue;
    }

    public function getGiftCardCode(): ?string
    {
        return $this->giftCardCode;
    }

    public function setGiftCardCode(?string $giftCardCode): void
    {
        $this->giftCardCode = $giftCardCode;
    }
}
