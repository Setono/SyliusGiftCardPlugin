<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

class GiftCardInformation implements GiftCardInformationInterface
{
    public function __construct(protected int $amount, protected ?string $customMessage = null)
    {
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }
}
