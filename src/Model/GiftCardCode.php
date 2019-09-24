<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

class GiftCardCode implements GiftCardCodeInterface
{
    /** @var int|null */
    protected $id;

    /**
     * Item of currentOrder related to this GiftCardCode
     * (orderItem's amount === GiftCardCode's amount)
     *
     * @var OrderItemInterface|null
     */
    protected $orderItem;

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

    /** @var GiftCardInterface|null */
    protected $giftCard;

    /** @var bool */
    protected $active = false;

    /**
     * Initial amount. Not changeable
     *
     * @var int|null
     */
    protected $initialAmount = 0;

    /**
     * Current amount (initial minus spent). Changeable
     *
     * @var int|null
     */
    protected $amount = 0;

    /** @var string|null */
    protected $currencyCode;

    /** @var ChannelInterface|null */
    protected $channel;

    public function __construct()
    {
        $this->usedInOrders = new ArrayCollection();
    }

    public function isDeletable(): bool
    {
        return null === $this->orderItem;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderItem(): ?OrderItemInterface
    {
        return $this->orderItem;
    }

    public function setOrderItem(?OrderItemInterface $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    public function getInitialAmount(): ?int
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(?int $initialAmount): void
    {
        $this->initialAmount = $initialAmount;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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

    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }
}
