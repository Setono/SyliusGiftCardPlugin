<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;
use Sylius\Component\Channel\Model\ChannelInterface;

final class ChannelMismatchException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @throws StringsException
     */
    public function __construct(ChannelInterface $actualChannel, ChannelInterface $expectedChannel)
    {
        parent::__construct(sprintf(
            'Expected channel was "%s", given "%s"', $expectedChannel->getName(), $actualChannel->getName()
        ));
    }
}
