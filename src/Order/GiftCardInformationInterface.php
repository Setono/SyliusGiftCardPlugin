<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;

interface GiftCardInformationInterface
{
    /**
     * Null while the customer has not filled in an amount yet: the form's data mapper writes the submitted
     * value into this object before validation runs, so a blank amount has to be representable here
     */
    public function getAmount(): ?int;

    public function setAmount(?int $amount): void;

    public function getCustomMessage(): ?string;

    public function setCustomMessage(?string $customMessage): void;

    public function getDesign(): ?GiftCardDesignInterface;

    public function setDesign(?GiftCardDesignInterface $design): void;
}
