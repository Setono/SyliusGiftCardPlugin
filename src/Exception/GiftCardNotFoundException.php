<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;

final class GiftCardNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param object|string|mixed $giftCard
     *
     * @throws StringsException
     */
    public function __construct($giftCard)
    {
        $message = 'The gift card was not found';

        if (is_string($giftCard)) {
            $message = sprintf('The gift card with code "%s" was not found', $giftCard);
        }

        parent::__construct($message);
    }
}
