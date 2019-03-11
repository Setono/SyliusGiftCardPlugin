<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

interface GiftCardCodeGeneratorInterface
{
    public const DEFAULT_CODE_LENGTH = 9;

    public function generate(int $codeLength = self::DEFAULT_CODE_LENGTH): string;
}
