<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use function sprintf;

final class GiftCardNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    public function __construct(private readonly string $giftCard)
    {
        parent::__construct(sprintf('The gift card with code "%s" was not found', $this->giftCard));
    }

    public function getGiftCard(): string
    {
        return $this->giftCard;
    }
}
