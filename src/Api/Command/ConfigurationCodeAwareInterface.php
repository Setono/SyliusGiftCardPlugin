<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

use Sylius\Bundle\ApiBundle\Command\CommandAwareDataTransformerInterface;

interface ConfigurationCodeAwareInterface extends CommandAwareDataTransformerInterface
{
    public function getConfigurationCode(): ?string;

    public function setConfigurationCode(?string $configurationCode): void;
}
