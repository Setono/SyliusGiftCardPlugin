<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

class GiftCardCode implements GiftCardCodeInterface
{
    /** @var int|null */
    protected $id;

    /** @var OrderItemInterface|null */
    protected $orderItem;

    /** @var Collection|OrderInterface[] */
    protected $usedInOrders;

    /** @var OrderInterface|null */
    protected $currentOrder;

    /** @var string|null */
    protected $code;

    /** @var GiftCardInterface|null */
    protected $giftCard;

    /** @var bool */
    protected $isActive = false;

    /** @var int */
    protected $amount = 0;

    /** @var string|null */
    protected $channelCode;

    public function __construct()
    {
        $this->usedInOrders = new ArrayCollection();
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

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getChannelCode(): ?string
    {
        return $this->channelCode;
    }

    public function setChannelCode(?string $channelCode): void
    {
        $this->channelCode = $channelCode;
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
}
