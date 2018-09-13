<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface GiftCardCodeInterface extends ResourceInterface
{
    public function getOrderItem(): ?OrderItemInterface;

    public function setOrderItem(?OrderItemInterface $orderItem): void;

    public function getCode(): ?string;

    public function setCode(?string $code): void;

    public function getGiftCard(): GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;

    public function getAmount(): int;

    public function setAmount(int $amount): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function getChannelCode(): ?string;

    public function setChannelCode(?string $channelCode): void;

    public function getUsedInOrders(): Collection;

    public function addUsedInOrder(OrderInterface $order): void;

    public function removeUsedInOrder(OrderInterface $order): void;

    public function hasUsedInOrder(OrderInterface $order): bool;

    public function getCurrentOrder(): ?OrderInterface;

    public function setCurrentOrder(?OrderInterface $currentOrder): void;
}
