<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface GiftCardCodeInterface extends ResourceInterface, CodeAwareInterface, ChannelAwareInterface
{
    /**
     * Admin can't remove items that was purchased
     * with real money. Only generated items can be removed.
     */
    public function isDeletable(): bool;

    public function getOrderItem(): ?OrderItemInterface;

    public function setOrderItem(?OrderItemInterface $orderItem): void;

    public function getGiftCard(): ?GiftCardInterface;

    public function setGiftCard(?GiftCardInterface $giftCard): void;

    public function getInitialAmount(): ?int;

    public function setInitialAmount(?int $initialAmount): void;

    public function getAmount(): ?int;

    public function setAmount(?int $amount): void;

    public function isActive(): bool;

    public function setActive(bool $active): void;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(?string $currencyCode): void;

    public function isUsedInOrders(): bool;

    public function getUsedInOrders(): Collection;

    public function addUsedInOrder(OrderInterface $order): void;

    public function removeUsedInOrder(OrderInterface $order): void;

    public function hasUsedInOrder(OrderInterface $order): bool;

    public function getCurrentOrder(): ?OrderInterface;

    public function setCurrentOrder(?OrderInterface $currentOrder): void;
}
