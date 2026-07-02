<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

final class GiftCardCurrencyMismatchException extends InvalidArgumentException implements ExceptionInterface
{
    public function __construct(
        private readonly GiftCardInterface $giftCard,
        private readonly string $orderCurrencyCode,
    ) {
        parent::__construct(sprintf(
            'The gift card "%s" is in currency "%s" but the order currency is "%s"',
            (string) $giftCard->getCode(),
            (string) $giftCard->getCurrencyCode(),
            $orderCurrencyCode,
        ));
    }

    public function getGiftCard(): GiftCardInterface
    {
        return $this->giftCard;
    }

    public function getOrderCurrencyCode(): string
    {
        return $this->orderCurrencyCode;
    }
}
