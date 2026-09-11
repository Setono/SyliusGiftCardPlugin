<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use RuntimeException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

final class InsufficientGiftCardBalanceException extends RuntimeException implements ExceptionInterface
{
    public function __construct(
        private readonly GiftCardInterface $giftCard,
        private readonly int $requestedAmount,
    ) {
        parent::__construct(sprintf(
            'The gift card "%s" has a balance of %d but %d was requested',
            (string) $giftCard->getCode(),
            $giftCard->getAmount(),
            $requestedAmount,
        ));
    }

    public function getGiftCard(): GiftCardInterface
    {
        return $this->giftCard;
    }

    public function getRequestedAmount(): int
    {
        return $this->requestedAmount;
    }
}
