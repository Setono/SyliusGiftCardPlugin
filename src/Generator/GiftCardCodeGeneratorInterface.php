<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

interface GiftCardCodeGeneratorInterface
{
    public function generate(): string;
}
