<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order\Dto;

interface AddGiftCardToCartInformationInterface
{
    public function getAmount(): int;

    public function setAmount(int $amount): void;

    public function getCustomMessage(): ?string;

    public function setCustomMessage(?string $customMessage): void;
}
