<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;

class GiftCard implements GiftCardInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    /** Null only while a blank submission is being validated; a persisted card always holds an amount */
    protected ?int $amount = 0;

    protected int $initialAmount = 0;

    protected ?string $currencyCode = null;

    protected ?ChannelInterface $channel = null;

    protected GiftCardDeliveryType $deliveryType = GiftCardDeliveryType::Virtual;

    protected ?GiftCardDesignInterface $design = null;

    protected ?OrderItemUnitInterface $orderItemUnit = null;

    protected ?CustomerInterface $customer = null;

    protected ?string $customMessage = null;

    protected ?\DateTimeInterface $expiresAt = null;

    protected int $version = 1;

    /** @var Collection<array-key, OrderInterface> */
    protected Collection $appliedOrders;

    /** @var Collection<array-key, GiftCardTransactionInterface> */
    protected Collection $transactions;

    /**
     * Not persisted. Used by the admin create form to decide whether to notify the customer by email
     */
    protected bool $sendNotificationEmail = true;

    public function __construct()
    {
        $this->appliedOrders = new ArrayCollection();
        $this->transactions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function isUsable(): bool
    {
        return $this->enabled && !$this->isExpired() && $this->getAmount() > 0;
    }

    public function isPending(): bool
    {
        return !$this->enabled && $this->transactions->isEmpty() && null !== $this->orderItemUnit;
    }

    public function getStatus(?\DateTimeInterface $date = null): GiftCardStatus
    {
        if ($this->isPending()) {
            // A bought card is only issued once its order is paid, so the card of an order cancelled before then is
            // still pending by isPending(), though it waits for nothing any more (sylius:cancel-unpaid-orders leaves
            // one behind for every expired order with a gift card in it)
            return OrderInterface::STATE_CANCELLED === $this->getOrder()?->getState() ? GiftCardStatus::Disabled : GiftCardStatus::Pending;
        }

        if (!$this->enabled) {
            return GiftCardStatus::Disabled;
        }

        if ($this->getAmount() <= 0) {
            return GiftCardStatus::Spent;
        }

        if ($this->isExpired($date)) {
            return GiftCardStatus::Expired;
        }

        return GiftCardStatus::Usable;
    }

    public function isDeletable(): bool
    {
        if ($this->isPending()) {
            return true;
        }

        // Anything else only while nothing has happened to it. Its balance being back where it started is not enough:
        // a card restored after a cancelled order, or adjusted down and up again, has a history, and deleting the card
        // would take its ledger rows and its links to those orders with it (#491). A balance that moved still counts,
        // for a card created without the balance operator, whose ledger does not account for it. The cheap checks go
        // first, so the grid only loads the collections of the cards that pass them
        if (null !== $this->orderItemUnit || $this->amount !== $this->initialAmount || $this->hasAppliedOrders()) {
            return false;
        }

        foreach ($this->transactions as $transaction) {
            if (GiftCardTransactionInterface::TYPE_ISSUE !== $transaction->getType()) {
                return false;
            }

            // An issuance naming an order is that of a card a customer paid for. Deleting the order sets the card's
            // unit to null, which is all the check above sees, but the row keeps its copy of the order's number (#494)
            if (null !== $transaction->getOrderNumber()) {
                return false;
            }
        }

        return true;
    }

    public function getOrderItemUnit(): ?OrderItemUnitInterface
    {
        return $this->orderItemUnit;
    }

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItemUnit): void
    {
        if ($this->orderItemUnit === $orderItemUnit) {
            return;
        }

        $this->orderItemUnit = $orderItemUnit;

        $orderItemUnit?->setGiftCard($this);
    }

    public function getOrder(): ?OrderInterface
    {
        $orderItemUnit = $this->getOrderItemUnit();
        if (null === $orderItemUnit) {
            return null;
        }

        /** @var OrderInterface|null $order */
        $order = $orderItemUnit->getOrderItem()->getOrder();

        return $order;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getAmount(): int
    {
        return $this->amount ?? 0;
    }

    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    public function getInitialAmount(): int
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(int $initialAmount): void
    {
        $this->initialAmount = $initialAmount;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getDeliveryType(): GiftCardDeliveryType
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(GiftCardDeliveryType $deliveryType): void
    {
        $this->deliveryType = $deliveryType;
    }

    public function getDesign(): ?GiftCardDesignInterface
    {
        return $this->design;
    }

    public function setDesign(?GiftCardDesignInterface $design): void
    {
        $this->design = $design;
    }

    public function getAppliedOrders(): Collection
    {
        return $this->appliedOrders;
    }

    public function hasAppliedOrders(): bool
    {
        return !$this->appliedOrders->isEmpty();
    }

    public function addAppliedOrder(OrderInterface $order): void
    {
        if (!$this->hasAppliedOrder($order)) {
            $this->appliedOrders->add($order);
        }
    }

    public function removeAppliedOrder(OrderInterface $order): void
    {
        if ($this->hasAppliedOrder($order)) {
            $this->appliedOrders->removeElement($order);
        }
    }

    public function hasAppliedOrder(OrderInterface $order): bool
    {
        return $this->appliedOrders->contains($order);
    }

    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(GiftCardTransactionInterface $transaction): void
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setGiftCard($this);
        }
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function isExpired(?\DateTimeInterface $date = null): bool
    {
        $expiresAt = $this->expiresAt;
        if (null === $expiresAt) {
            return false;
        }

        $date ??= new \DateTimeImmutable();

        return $date > $expiresAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getSendNotificationEmail(): bool
    {
        return $this->sendNotificationEmail;
    }

    public function setSendNotificationEmail(bool $sendNotificationEmail = true): void
    {
        $this->sendNotificationEmail = $sendNotificationEmail;
    }
}
