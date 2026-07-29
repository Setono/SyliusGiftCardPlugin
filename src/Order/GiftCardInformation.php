<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

final class GiftCardInformation implements GiftCardInformationInterface
{
    private int $amount;

    private ?string $customMessage;

    public function __construct(int $amount, string $customMessage = null)
    {
        $this->amount = $amount;
        $this->customMessage = $customMessage;
    }

    #[\Override]
    public function getAmount(): int
    {
        return $this->amount;
    }

    #[\Override]
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    #[\Override]
    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    #[\Override]
    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }
}
