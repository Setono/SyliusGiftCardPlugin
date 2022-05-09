<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

interface DefaultGiftCardTemplateContentProviderInterface
{
    public function getContent(): string;
}
