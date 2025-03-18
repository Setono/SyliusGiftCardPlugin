<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

interface ConfigurationCodeAwareInterface
{
    public function getConfigurationCode(): ?string;

    public function setConfigurationCode(?string $configurationCode): void;
}
