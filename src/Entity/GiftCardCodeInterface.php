<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface GiftCardCodeInterface extends ResourceInterface
{
    public function getOrder(): ?OrderInterface;

    public function setOrder(?OrderInterface $order): void;

    public function getCode(): ?string;

    public function setCode(?string $code): void;

    public function getGiftCard(): GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;
}
