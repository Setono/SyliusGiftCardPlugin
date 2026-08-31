<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use RuntimeException;
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

    protected ?OrderItemUnitInterface $orderItemUnit = null;

    protected ?CustomerInterface $customer = null;

    /**
     * @var Collection|OrderInterface[]
     * @psalm-var Collection<array-key, OrderInterface>
     */
    protected Collection $appliedOrders;

    protected ?string $code = null;

    protected ?int $initialAmount = null;

    protected int $amount = 0;

    protected ?string $currencyCode = null;

    protected ?ChannelInterface $channel = null;

    protected ?string $customMessage = null;

    protected ?string $origin = null;

    protected ?DateTimeInterface $expiresAt = null;

    protected bool $sendNotificationEmail = true;

    public function __construct()
    {
        $this->appliedOrders = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->code;
    }

    #[\Override]
    public function getCode(): ?string
    {
        return $this->code;
    }

    #[\Override]
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function isDeletable(): bool
    {
        return null === $this->orderItemUnit;
    }

    #[\Override]
    public function getOrderItemUnit(): ?OrderItemUnitInterface
    {
        return $this->orderItemUnit;
    }

    #[\Override]
    public function setOrderItemUnit(OrderItemUnitInterface $orderItemUnit): void
    {
        if ($this->orderItemUnit === $orderItemUnit) {
            return;
        }

        $this->orderItemUnit = $orderItemUnit;

        $orderItemUnit->setGiftCard($this);
    }

    #[\Override]
    public function getOrder(): ?OrderInterface
    {
        $orderItemUnit = $this->getOrderItemUnit();
        if (null === $orderItemUnit) {
            return null;
        }

        /** @var OrderInterface|null $order */
        $order = $orderItemUnit->getOrderItem()->getOrder();
        if (null === $order) {
            return null;
        }

        return $order;
    }

    #[\Override]
    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    #[\Override]
    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    #[\Override]
    public function getInitialAmount(): ?int
    {
        return $this->initialAmount;
    }

    #[\Override]
    public function setInitialAmount(int $initialAmount): void
    {
        if (null !== $this->initialAmount) {
            throw new RuntimeException('You cannot change the initial amount of a gift card');
        }

        $this->initialAmount = $initialAmount;
    }

    #[\Override]
    public function getAmount(): int
    {
        return $this->amount;
    }

    #[\Override]
    public function setAmount(int $amount): void
    {
        if (null === $this->initialAmount) {
            $this->setInitialAmount($amount);
        }

        $this->amount = $amount;
    }

    #[\Override]
    public function getAppliedOrders(): Collection
    {
        return $this->appliedOrders;
    }

    #[\Override]
    public function hasAppliedOrders(): bool
    {
        return !$this->getAppliedOrders()->isEmpty();
    }

    #[\Override]
    public function hasAppliedCompletedOrders(): bool
    {
        foreach ($this->appliedOrders as $appliedOrder) {
            if ($appliedOrder->isCheckoutCompleted()) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function addAppliedOrder(OrderInterface $order): void
    {
        if (!$this->hasAppliedOrder($order)) {
            $this->appliedOrders->add($order);
        }
    }

    #[\Override]
    public function removeAppliedOrder(OrderInterface $order): void
    {
        if ($this->hasAppliedOrder($order)) {
            $this->appliedOrders->removeElement($order);
        }
    }

    #[\Override]
    public function hasAppliedOrder(OrderInterface $order): bool
    {
        return $this->appliedOrders->contains($order);
    }

    #[\Override]
    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    #[\Override]
    public function setCurrencyCode(string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    #[\Override]
    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    #[\Override]
    public function setChannel(ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    #[\Override]
    public function getCustomerIdentification(): ?array
    {
        $customer = $this->getCustomer();
        if (null === $customer) {
            return null;
        }

        return [
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
        ];
    }

    #[\Override]
    public function getOrderIdentification(): ?array
    {
        $order = $this->getOrder();
        if (null === $order) {
            return null;
        }

        /** @var mixed $orderId */
        $orderId = $order->getId();
        $orderNumber = $order->getNumber();

        if (null === $orderId || null === $orderNumber) {
            return null;
        }

        return [
            'id' => $orderId,
            'number' => $orderNumber,
        ];
    }

    #[\Override]
    public function getChannelCode(): ?string
    {
        $channel = $this->getChannel();
        if (null === $channel) {
            return null;
        }

        return $channel->getCode();
    }

    #[\Override]
    public function hasOrderOrCustomer(): bool
    {
        return null !== $this->getCustomer() || null !== $this->getOrder();
    }

    #[\Override]
    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    #[\Override]
    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }

    #[\Override]
    public function setOrigin(?string $origin): void
    {
        $this->origin = $origin;
    }

    #[\Override]
    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    #[\Override]
    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    #[\Override]
    public function setExpiresAt(?DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    #[\Override]
    public function isExpired(?DateTimeInterface $date = null): bool
    {
        if (null === $date) {
            $date = new DateTime();
        }

        $giftCardValidUntil = $this->getExpiresAt();
        if (null === $giftCardValidUntil) {
            return false;
        }

        return $date > $giftCardValidUntil;
    }

    #[\Override]
    public function getSendNotificationEmail(): bool
    {
        return $this->sendNotificationEmail;
    }

    #[\Override]
    public function setSendNotificationEmail(bool $sendNotificationEmail = true): void
    {
        $this->sendNotificationEmail = $sendNotificationEmail;
    }
}
