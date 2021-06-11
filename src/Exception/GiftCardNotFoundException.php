<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use function sprintf;

final class GiftCardNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    private string $giftCard;

    public function __construct(string $giftCard)
    {
        $message = sprintf('The gift card with code "%s" was not found', $giftCard);

        parent::__construct($message);
    }

    public function getGiftCard(): string
    {
        return $this->giftCard;
    }
}
