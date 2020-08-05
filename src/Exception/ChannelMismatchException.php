<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use InvalidArgumentException;
use function Safe\sprintf;
use Sylius\Component\Channel\Model\ChannelInterface;

final class ChannelMismatchException extends InvalidArgumentException implements ExceptionInterface
{
    /** @var ChannelInterface */
    private $actualChannel;

    /** @var ChannelInterface */
    private $expectedChannel;

    public function __construct(ChannelInterface $actualChannel, ChannelInterface $expectedChannel)
    {
        parent::__construct(sprintf(
            'Expected channel was "%s", given "%s"', $expectedChannel->getName(), $actualChannel->getName()
        ));
        $this->actualChannel = $actualChannel;
        $this->expectedChannel = $expectedChannel;
    }

    public function getActualChannel(): ChannelInterface
    {
        return $this->actualChannel;
    }

    public function getExpectedChannel(): ChannelInterface
    {
        return $this->expectedChannel;
    }
}
