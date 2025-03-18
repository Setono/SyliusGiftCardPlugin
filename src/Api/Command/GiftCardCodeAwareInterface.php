<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

interface GiftCardCodeAwareInterface
{
    public function getGiftCardCode(): ?string;

    public function setGiftCardCode(?string $giftCardCode): void;
}
