<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use RuntimeException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;

class GiftCard implements GiftCardInterface
{
    use ToggleableTrait;

    /** @var int|null */
    protected $id;

    /** @var OrderItemUnitInterface|null */
    protected $orderItemUnit;

    /**
     * Orders payed by this GiftCardCode
     *
     * @var Collection|OrderInterface[]
     */
    protected $usedInOrders;

    /**
     * Cart/Order this GiftCardCode is currently applying
     *
     * @var OrderInterface|null
     */
    protected $currentOrder;

    /** @var string|null */
    protected $code;

    /** @var int|null */
    protected $initialAmount;

    /**
     * Current amount (initial minus spent)
     *
     * @var int|null
     */
    protected $amount;

    /** @var string|null */
    protected $currencyCode;

    /** @var ChannelInterface|null */
    protected $channel;

    public function __construct()
    {
        $this->usedInOrders = new ArrayCollection();
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

    public function setOrderItemUnit(?OrderItemUnitInterface $orderItem): void
    {
        $this->orderItemUnit = $orderItem;
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
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function isUsedInOrders(): bool
    {
        return $this->usedInOrders->count() > 0;
    }

    public function getUsedInOrders(): Collection
    {
        return $this->usedInOrders;
    }

    public function addUsedInOrder(OrderInterface $order): void
    {
        if (!$this->hasUsedInOrder($order)) {
            $this->usedInOrders->add($order);
        }
    }

    public function removeUsedInOrder(OrderInterface $order): void
    {
        if ($this->hasUsedInOrder($order)) {
            $this->usedInOrders->removeElement($order);
        }
    }

    public function hasUsedInOrder(OrderInterface $order): bool
    {
        return $this->usedInOrders->contains($order);
    }

    public function getCurrentOrder(): ?OrderInterface
    {
        return $this->currentOrder;
    }

    public function setCurrentOrder(?OrderInterface $currentOrder): void
    {
        $this->currentOrder = $currentOrder;
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
