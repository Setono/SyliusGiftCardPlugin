<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\ValidateAddToCartCommandClassPass;
use Setono\SyliusGiftCardPlugin\SetonoSyliusGiftCardPlugin;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusGiftCardPluginTest extends TestCase
{
    /** @test */
    public function it_supports_doctrine_orm_only(): void
    {
        self::assertSame([SyliusResourceBundle::DRIVER_DOCTRINE_ORM], (new SetonoSyliusGiftCardPlugin())->getSupportedDrivers());
    }

    /**
     * Building the bundle registers the XML mapping of the plugin's models, for the ORM and nothing else, and the pass
     * that fails the container build when the add to cart command class does not fit the plugin
     *
     * @test
     */
    public function it_registers_its_compiler_passes(): void
    {
        $container = new ContainerBuilder();

        (new SetonoSyliusGiftCardPlugin())->build($container);

        $passes = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses(),
        );
        $passes = array_count_values($passes);

        self::assertSame(1, $passes[ValidateAddToCartCommandClassPass::class] ?? 0);
        self::assertSame(1, $passes[DoctrineOrmMappingsPass::class] ?? 0);
    }
}
