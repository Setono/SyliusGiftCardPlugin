<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardConfigurationFactory implements GiftCardConfigurationFactoryInterface
{
    private FactoryInterface $decoratedFactory;

    private string $defaultOrientation;

    private string $defaultPageSize;

    public function __construct(
        FactoryInterface $decoratedFactory,
        string $defaultOrientation,
        string $defaultPageSize
    ) {
        $this->decoratedFactory = $decoratedFactory;
        $this->defaultOrientation = $defaultOrientation;
        $this->defaultPageSize = $defaultPageSize;
    }

    public function createNew(): GiftCardConfigurationInterface
    {
        /** @var GiftCardConfigurationInterface $configuration */
        $configuration = $this->decoratedFactory->createNew();

        $configuration->setOrientation($this->defaultOrientation);
        $configuration->setPageSize($this->defaultPageSize);

        return $configuration;
    }
}
