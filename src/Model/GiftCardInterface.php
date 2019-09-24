<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface GiftCardInterface extends ResourceInterface, ToggleableInterface
{
    public function getCode(): ?string;

    public function setCode(string $code): void;

    /**
     * Admin can't remove items that was purchased
     * with real money. Only generated items can be removed.
     */
    public function isDeletable(): bool;

    /**
     * This is the order item unit where this gift card was bought
     * If it's null it means that the gift card was not bought,
     * but created in the backend or through API
     */
    public function getOrderItemUnit(): ?OrderItemUnitInterface;

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItem): void;

    public function getInitialAmount(): ?int;

    /**
     * Should only be possible to set this once
     */
    public function setInitialAmount(int $initialAmount): void;

    public function getAmount(): ?int;

    public function setAmount(int $amount): void;

    public function isEnabled(): bool;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(string $currencyCode): void;

    public function isUsedInOrders(): bool;

    public function getUsedInOrders(): Collection;

    public function addUsedInOrder(OrderInterface $order): void;

    public function removeUsedInOrder(OrderInterface $order): void;

    public function hasUsedInOrder(OrderInterface $order): bool;

    public function getCurrentOrder(): ?OrderInterface;

    public function setCurrentOrder(?OrderInterface $currentOrder): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(ChannelInterface $channel): void;
}
