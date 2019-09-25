<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface GiftCardInterface extends ResourceInterface, ToggleableInterface, CodeAwareInterface
{
    /**
     * Admin can't remove items that was purchased with real money. Only generated items can be removed
     */
    public function isDeletable(): bool;

    /**
     * This is the order item unit where this gift card was bought
     * If it's null it means that the gift card was not bought,
     * but created in the backend or through API
     */
    public function getOrderItemUnit(): ?OrderItemUnitInterface;

    public function setOrderItemUnit(OrderItemUnitInterface $orderItem): void;

    /**
     * This is the current amount available on this gift card
     */
    public function getAmount(): ?int;

    public function setAmount(int $amount): void;

    /**
     * This is the original value of the gift card
     */
    public function getInitialAmount(): ?int;

    /**
     * Should only be possible to set this once
     */
    public function setInitialAmount(int $initialAmount): void;

    public function isEnabled(): bool;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(string $currencyCode): void;

    /**
     * The order where this gift card is being applied right now
     */
    public function getCurrentOrder(): ?OrderInterface;

    public function setCurrentOrder(?OrderInterface $currentOrder): void;

    /**
     * Orders where this gift card was applied
     *
     * @return Collection|OrderInterface[]
     */
    public function getAppliedOrders(): Collection;

    /**
     * Returns true if this gift card ever been applied to an order
     */
    public function hasAppliedOrders(): bool;

    public function addAppliedOrder(OrderInterface $order): void;

    public function removeAppliedOrder(OrderInterface $order): void;

    public function hasAppliedOrder(OrderInterface $order): bool;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(ChannelInterface $channel): void;
}
