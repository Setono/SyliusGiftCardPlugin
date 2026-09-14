<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Extension;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Twig\Extension\GiftCardCodeExtension;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;
use Twig\Extension\ExtensionInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Test\IntegrationTestCase;

/**
 * Renders the templates in ./Fixtures through a real Twig environment carrying the extension and its runtime,
 * so the filter is exercised the way a template uses it rather than by inspecting the extension's definition
 */
final class GiftCardCodeExtensionTest extends IntegrationTestCase
{
    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/Fixtures';
    }

    /**
     * Twig before 3.13 asks for the directory through this method instead
     */
    protected function getFixturesDir(): string
    {
        return self::getFixturesDirectory();
    }

    /**
     * @return list<ExtensionInterface>
     */
    protected function getExtensions(): array
    {
        return [new GiftCardCodeExtension()];
    }

    /**
     * @return list<RuntimeLoaderInterface>
     */
    protected function getRuntimeLoaders(): array
    {
        return [
            new FactoryRuntimeLoader([
                GiftCardCodeRuntime::class => static fn (): GiftCardCodeRuntime => new GiftCardCodeRuntime(new GiftCardCodeNormalizer()),
            ]),
        ];
    }
}
