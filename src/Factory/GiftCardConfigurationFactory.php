<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardConfigurationFactory implements GiftCardConfigurationFactoryInterface
{
    private FactoryInterface $decoratedFactory;

    private FactoryInterface $imageFactory;

    public function __construct(FactoryInterface $decoratedFactory, FactoryInterface $imageFactory)
    {
        $this->decoratedFactory = $decoratedFactory;
        $this->imageFactory = $imageFactory;
    }

    public function createNew(): GiftCardConfigurationInterface
    {
        /** @var GiftCardConfigurationInterface $configuration */
        $configuration = $this->decoratedFactory->createNew();

        /** @var GiftCardConfigurationImageInterface $backgroundImage */
        $backgroundImage = $this->imageFactory->createNew();
        $configuration->setBackgroundImage($backgroundImage);

        return $configuration;
    }
}
