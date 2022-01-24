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

    private string $defaultOrientation;

    private string $defaultPageSize;

    public function __construct(
        FactoryInterface $decoratedFactory,
        FactoryInterface $imageFactory,
        string $defaultOrientation,
        string $defaultPageSize
    ) {
        $this->decoratedFactory = $decoratedFactory;
        $this->imageFactory = $imageFactory;
        $this->defaultOrientation = $defaultOrientation;
        $this->defaultPageSize = $defaultPageSize;
    }

    public function createNew(): GiftCardConfigurationInterface
    {
        /** @var GiftCardConfigurationInterface $configuration */
        $configuration = $this->decoratedFactory->createNew();

        /** @var GiftCardConfigurationImageInterface $backgroundImage */
        $backgroundImage = $this->imageFactory->createNew();
        $configuration->setBackgroundImage($backgroundImage);

        $configuration->setOrientation($this->defaultOrientation);
        $configuration->setPageSize($this->defaultPageSize);

        return $configuration;
    }
}
