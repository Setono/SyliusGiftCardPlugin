<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;

final class GiftCardNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    /** @var string */
    private $giftCard;

    /**
     * @throws StringsException
     */
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
