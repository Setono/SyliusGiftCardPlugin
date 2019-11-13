<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use RuntimeException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;

class GiftCard implements GiftCardInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    /** @var int */
    protected $id;

    /** @var OrderItemUnitInterface|null */
    protected $orderItemUnit;

    /** @var Collection|OrderInterface[] */
    protected $appliedOrders;

    /** @var string|null */
    protected $code;

    /** @var int */
    protected $initialAmount;

    /** @var int */
    protected $amount = 0;

    /** @var string */
    protected $currencyCode;

    /** @var ChannelInterface */
    protected $channel;

    public function __construct()
    {
        $this->appliedOrders = new ArrayCollection();
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

    public function setOrderItemUnit(OrderItemUnitInterface $orderItem): void
    {
        $this->orderItemUnit = $orderItem;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
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
        $this->setAmount($initialAmount);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
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
}
