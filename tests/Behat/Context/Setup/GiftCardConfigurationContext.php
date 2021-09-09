<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardConfigurationFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardConfigurationContext implements Context
{
    private RepositoryInterface $giftCardConfigurationRepository;

    private GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory;

    public function __construct(
        RepositoryInterface $giftCardConfigurationRepository,
        GiftCardConfigurationFactoryInterface $giftCardConfigurationFactory
    ) {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
        $this->giftCardConfigurationFactory = $giftCardConfigurationFactory;
    }

    /**
     * @Given /^the store has a gift card configuration with code "([^"]+)"$/
     */
    public function theStoreHasGiftCardConfigurationWithCode(string $code): void
    {
        $giftCardConfiguration = $this->giftCardConfigurationFactory->createNew();
        $giftCardConfiguration->setCode($code);
        $giftCardConfiguration->enable();
        foreach ($giftCardConfiguration->getImages() as $image) {
            $giftCardConfiguration->removeImage($image);
        }

        $this->giftCardConfigurationRepository->add($giftCardConfiguration);
    }
}
