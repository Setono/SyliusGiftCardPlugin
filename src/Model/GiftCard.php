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
     *
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isDeletable(): bool
    {
        return null === $this->orderItemUnit;
    }

    public function getOrderItemUnit(): ?OrderItemUnitInterface
    {
        return $this->orderItemUnit;
    }

    public function setOrderItemUnit(OrderItemUnitInterface $orderItemUnit): void
    {
        if ($this->orderItemUnit === $orderItemUnit) {
            return;
        }

        $this->orderItemUnit = $orderItemUnit;

        $orderItemUnit->setGiftCard($this);
    }

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

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getInitialAmount(): ?int
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(int $initialAmount): void
    {
        if (null !== $this->initialAmount) {
            throw new RuntimeException('You cannot change the initial amount of a gift card');
        }

        $this->initialAmount = $initialAmount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        if (null === $this->initialAmount) {
            $this->setInitialAmount($amount);
        }

        $this->amount = $amount;
    }

    public function getAppliedOrders(): Collection
    {
        return $this->appliedOrders;
    }

    public function hasAppliedOrders(): bool
    {
        return !$this->getAppliedOrders()->isEmpty();
    }

    public function hasAppliedCompletedOrders(): bool
    {
        foreach ($this->appliedOrders as $appliedOrder) {
            if ($appliedOrder->isCheckoutCompleted()) {
                return true;
            }
        }

        return false;
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

    /**
     * API specific methods. See src/Resources/config/serializer/Model.GiftCard.yml
     */
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

    public function getOrderIdentification(): ?array
    {
        $order = $this->getOrder();
        if (null === $order) {
            return null;
        }

        return [
            'id' => $order->getId(),
            'number' => $order->getNumber(),
        ];
    }

    public function getChannelCode(): ?string
    {
        $channel = $this->getChannel();
        if (null === $channel) {
            return null;
        }

        return $channel->getCode();
    }

    public function hasOrderOrCustomer(): bool
    {
        return null !== $this->getCustomer() || null !== $this->getOrder();
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): void
    {
        $this->customMessage = $customMessage;
    }

    public function setOrigin(?string $origin): void
    {
        $this->origin = $origin;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function isExpired(DateTimeInterface $date = null): bool
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

    public function getSendNotificationEmail(): bool
    {
        return $this->sendNotificationEmail;
    }

    public function setSendNotificationEmail(bool $sendNotificationEmail = true): void
    {
        $this->sendNotificationEmail = $sendNotificationEmail;
    }
}
