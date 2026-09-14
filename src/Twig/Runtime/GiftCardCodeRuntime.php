<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardCodeRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly GiftCardCodeNormalizerInterface $codeNormalizer)
    {
    }
}
