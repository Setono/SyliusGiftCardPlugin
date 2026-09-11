<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface GiftCardInterface extends ResourceInterface, ToggleableInterface, CodeAwareInterface, TimestampableInterface
{
    public function __toString(): string;

    public function getId(): ?int;

    /**
     * A gift card is usable (i.e. can be applied to an order) when it is enabled, not expired and has a positive balance.
     * This is THE method to call when deciding whether a gift card can pay for anything
     */
    public function isUsable(): bool;

    /**
     * A gift card is pending when it has been created as part of a cart, but the order has not been paid yet.
     * Pending gift cards are disabled and have never had any balance mutations
     */
    public function isPending(): bool;

    /**
     * An admin cannot remove gift cards that were purchased with real money or (partially) spent.
     * Only pending or untouched gift cards can be removed
     */
    public function isDeletable(): bool;

    /**
     * This is the order item unit where this gift card was bought.
     * If it's null it means that the gift card was not bought in the shop, but created by an admin
     */
    public function getOrderItemUnit(): ?OrderItemUnitInterface;

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItemUnit): void;

    /**
     * This is a helper method that will return the order where the gift card was bought.
     * If the gift card was created manually, this will return null
     */
    public function getOrder(): ?OrderInterface;

    /**
     * Returns the customer this gift card was issued to.
     * It can return null since it's not a requirement to have an associated customer
     */
    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    /**
     * This is the current balance of this gift card in minor units (e.g. cents)
     */
    public function getAmount(): int;

    public function setAmount(int $amount): void;

    /**
     * This is the original value of the gift card in minor units (e.g. cents)
     */
    public function getInitialAmount(): int;

    public function setInitialAmount(int $initialAmount): void;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(string $currencyCode): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(ChannelInterface $channel): void;

    public function getDeliveryType(): GiftCardDeliveryType;

    public function setDeliveryType(GiftCardDeliveryType $deliveryType): void;

    public function getDesign(): ?GiftCardDesignInterface;

    public function setDesign(?GiftCardDesignInterface $design): void;

    /**
     * Orders where this gift card was applied
     *
     * @return Collection<array-key, OrderInterface>
     */
    public function getAppliedOrders(): Collection;

    public function hasAppliedOrders(): bool;

    public function addAppliedOrder(OrderInterface $order): void;

    public function removeAppliedOrder(OrderInterface $order): void;

    public function hasAppliedOrder(OrderInterface $order): bool;

    /**
     * The balance mutation ledger of this gift card
     *
     * @return Collection<array-key, GiftCardTransactionInterface>
     */
    public function getTransactions(): Collection;

    public function addTransaction(GiftCardTransactionInterface $transaction): void;

    public function getCustomMessage(): ?string;

    public function setCustomMessage(?string $customMessage): void;

    public function getExpiresAt(): ?\DateTimeInterface;

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void;

    public function isExpired(?\DateTimeInterface $date = null): bool;

    public function getVersion(): int;

    public function setVersion(int $version): void;

    public function getSendNotificationEmail(): bool;

    public function setSendNotificationEmail(bool $sendNotificationEmail = true): void;
}
