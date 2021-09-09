<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Transform;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardConfigurationContext implements Context
{
    private RepositoryInterface $giftCardConfigurationRepository;

    public function __construct(RepositoryInterface $giftCardConfigurationRepository)
    {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
    }

    /**
     * @Transform :giftCardConfiguration
     */
    public function getGiftCardConfigurationByCode(string $code): GiftCardConfigurationInterface
    {
        return $this->giftCardConfigurationRepository->findOneBy(['code' => $code]);
    }
}
