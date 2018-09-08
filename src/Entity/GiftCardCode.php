<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Entity;

use Sylius\Component\Core\Model\OrderInterface;

class GiftCardCode implements GiftCardCodeInterface
{
    /** @var int|null */
    protected $id;

    /** @var OrderInterface|null */
    protected $order;

    /** @var string|null */
    protected $code;

    /** @var GiftCardInterface|null */
    protected $giftCard;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getGiftCard(): GiftCardInterface
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardInterface $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
