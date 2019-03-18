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
    protected $active = false;

    /** @var int */
    protected $amount = 0;

    /** @var string|null */
    protected $currencyCode;

    /** @var ChannelInterface|null */
    protected $channel;

    public function __construct()
    {
        $this->usedInOrders = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderItem(): ?OrderItemInterface
    {
        return $this->orderItem;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderItem(?OrderItemInterface $orderItem): void
    {
        $this->orderItem = $orderItem;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getGiftCard(): ?GiftCardInterface
    {
        return $this->giftCard;
    }

    /**
     * {@inheritdoc}
     */
    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    /**
     * {@inheritdoc}
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * {@inheritdoc}
     */
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * {@inheritdoc}
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsedInOrders(): Collection
    {
        return $this->usedInOrders;
    }

    /**
     * {@inheritdoc}
     */
    public function addUsedInOrder(OrderInterface $order): void
    {
        if (!$this->hasUsedInOrder($order)) {
            $this->usedInOrders->add($order);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeUsedInOrder(OrderInterface $order): void
    {
        if ($this->hasUsedInOrder($order)) {
            $this->usedInOrders->removeElement($order);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasUsedInOrder(OrderInterface $order): bool
    {
        return $this->usedInOrders->contains($order);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentOrder(): ?OrderInterface
    {
        return $this->currentOrder;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentOrder(?OrderInterface $currentOrder): void
    {
        $this->currentOrder = $currentOrder;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }
}
