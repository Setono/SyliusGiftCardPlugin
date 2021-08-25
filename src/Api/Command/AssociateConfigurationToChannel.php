<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\Command;

class AssociateConfigurationToChannel implements ConfigurationCodeAwareInterface
{
    public string $configurationCode;

    public string $localeCode;

    public string $channelCode;

    public function __construct(string $localeCode, string $channelCode)
    {
        $this->localeCode = $localeCode;
        $this->channelCode = $channelCode;
    }

    public function getConfigurationCode(): string
    {
        return $this->configurationCode;
    }

    public function setConfigurationCode(string $configurationCode): void
    {
        $this->configurationCode = $configurationCode;
    }
}
