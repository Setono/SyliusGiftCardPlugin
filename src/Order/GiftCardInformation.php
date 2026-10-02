<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Order;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;

class GiftCardInformation implements GiftCardInformationInterface
{
    protected ?GiftCardDesignInterface $design = null;

    public function __construct(protected ?int $amount = null, protected ?string $customMessage = null)
    {
        $this->setCustomMessage($customMessage);
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    public function getCustomMessage(): ?string
    {
        return $this->customMessage;
    }

    public function setCustomMessage(?string $customMessage): void
    {
        // A browser submits every line break of a textarea as CR LF, but counts it as one character for maxlength and
        // the counter under the field, so line breaks are held as line feeds: the length validated is the one the
        // customer was shown. Symfony's TextareaType does the same since symfony/form 6.4.31, but not before
        $this->customMessage = null === $customMessage ? null : str_replace(["\r\n", "\r"], "\n", $customMessage);
    }

    public function getDesign(): ?GiftCardDesignInterface
    {
        return $this->design;
    }

    public function setDesign(?GiftCardDesignInterface $design): void
    {
        $this->design = $design;
    }
}
