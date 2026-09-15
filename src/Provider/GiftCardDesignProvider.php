<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Setono\SyliusGiftCardPlugin\Repository\GiftCardDesignRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final class GiftCardDesignProvider implements GiftCardDesignProviderInterface
{
    public function __construct(private readonly GiftCardDesignRepositoryInterface $designRepository)
    {
    }

    public function getDesigns(ChannelInterface $channel): array
    {
        return $this->designRepository->findEnabledByChannel($channel);
    }
}
