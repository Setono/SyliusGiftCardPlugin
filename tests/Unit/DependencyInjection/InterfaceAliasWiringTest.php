<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Applications change the plugin's behaviour by decorating one of its interfaces. Symfony applies a decorator of an
 * alias by re-pointing that alias only: the class it pointed at keeps its own id, undecorated. So a decorator of an
 * interface reaches exactly the services that ask for the interface, and anything asking for the class id silently
 * keeps using the original
 */
final class InterfaceAliasWiringTest extends TestCase
{
    private const PLUGIN_NAMESPACE = 'Setono\\SyliusGiftCardPlugin\\';

    /** @test */
    public function it_aliases_every_plugin_interface_its_services_implement(): void
    {
        $container = self::container();

        $missing = [];
        foreach (self::pluginServices($container) as $id => $class) {
            foreach (class_implements($class) as $interface) {
                if (str_starts_with($interface, self::PLUGIN_NAMESPACE) && !$container->hasAlias($interface)) {
                    $missing[] = sprintf('%s implements %s, but there is no %s service to decorate', $id, $interface, $interface);
                }
            }
        }

        self::assertSame([], $missing);
    }

    /** @test */
    public function it_refers_to_every_aliased_service_through_its_interface(): void
    {
        $container = self::container();
        $interfaceByClass = self::interfaceByAliasedClass($container);

        $bypassing = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            foreach (self::references($definition) as $reference) {
                if (isset($interfaceByClass[$reference])) {
                    $bypassing[] = sprintf('%s is built with %s instead of %s', $id, $reference, $interfaceByClass[$reference]);
                }
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            $target = (string) $alias;
            if (isset($interfaceByClass[$target]) && !interface_exists($id)) {
                $bypassing[] = sprintf('The %s alias points at %s instead of %s', $id, $target, $interfaceByClass[$target]);
            }
        }

        self::assertSame([], $bypassing, 'A decorator of the interface would never reach these');
    }

    /** @test */
    public function it_calls_the_winzou_callbacks_on_public_services_that_resolve_through_an_interface_alias(): void
    {
        $container = self::container();

        foreach (self::winzouCallbackServices($container) as $callback => $id) {
            self::assertTrue($container->has($id), sprintf('%s calls @%s, which does not exist', $callback, $id));

            // winzou fetches the service from the container when the callback runs
            $public = $container->hasAlias($id) ? $container->getAlias($id)->isPublic() : $container->getDefinition($id)->isPublic();
            self::assertTrue($public, sprintf('%s calls @%s, which is not public', $callback, $id));

            self::assertTrue(self::resolvesThroughAnInterface($container, $id), sprintf(
                '%s calls @%s, which does not go through an interface alias, so a decorator of the interface would not run there',
                $callback,
                $id,
            ));
        }
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $extension = new SetonoSyliusGiftCardExtension();
        $extension->load([], $container);
        $extension->prepend($container);

        return $container;
    }

    /**
     * The services the plugin registers under their class name, i.e. everything from its service files. Sylius also
     * registers each resource repository under its class, for autowiring; repositories are replaced through the
     * resource configuration rather than decorated, so those are left out
     *
     * @return array<string, class-string>
     */
    private static function pluginServices(ContainerBuilder $container): array
    {
        $services = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, self::PLUGIN_NAMESPACE) || $definition->hasTag('doctrine.repository_service')) {
                continue;
            }

            $class = $definition->getClass() ?? $id;
            self::assertTrue(class_exists($class), sprintf('The %s service is of class %s, which does not exist', $id, $class));

            $services[$id] = $class;
        }

        return $services;
    }

    /**
     * Maps each plugin class that an interface alias points at to that interface. Only class ids count: an interface
     * aliasing a resource service (GiftCardFactoryInterface -> setono_sylius_gift_card.factory.gift_card) follows
     * Sylius' convention, where the resource service id is the one to decorate
     *
     * @return array<string, string>
     */
    private static function interfaceByAliasedClass(ContainerBuilder $container): array
    {
        $interfaces = [];
        foreach ($container->getAliases() as $id => $alias) {
            $target = (string) $alias;
            if (interface_exists($id) && str_starts_with($id, self::PLUGIN_NAMESPACE) && str_starts_with($target, self::PLUGIN_NAMESPACE)) {
                $interfaces[$target] = $id;
            }
        }

        return $interfaces;
    }

    /**
     * @return list<string> the ids of every service the given value references, however deeply nested
     */
    private static function references(mixed $value): array
    {
        if ($value instanceof Reference) {
            return [(string) $value];
        }

        if ($value instanceof ArgumentInterface) {
            $value = $value->getValues();
        }

        if ($value instanceof Definition) {
            $value = [$value->getFactory(), $value->getArguments(), $value->getMethodCalls(), $value->getProperties(), $value->getConfigurator()];
        }

        if (!is_array($value)) {
            return [];
        }

        $references = [];
        foreach ($value as $item) {
            $references = [...$references, ...self::references($item)];
        }

        return $references;
    }

    /**
     * @return array<string, string> the name of each winzou callback prepend() registers => the id of the service it calls
     */
    private static function winzouCallbackServices(ContainerBuilder $container): array
    {
        $services = [];
        foreach ($container->getExtensionConfig('winzou_state_machine') as $graphs) {
            foreach ($graphs as $graph) {
                self::assertIsArray($graph);
                $positions = $graph['callbacks'] ?? null;
                self::assertIsArray($positions);

                foreach ($positions as $callbacks) {
                    self::assertIsArray($callbacks);

                    foreach ($callbacks as $name => $callback) {
                        self::assertIsArray($callback);
                        $do = $callback['do'] ?? null;
                        self::assertIsArray($do);
                        self::assertIsString($do[0] ?? null);
                        self::assertStringStartsWith('@', $do[0]);

                        $services[(string) $name] = substr($do[0], 1);
                    }
                }
            }
        }

        self::assertNotEmpty($services);

        return $services;
    }

    private static function resolvesThroughAnInterface(ContainerBuilder $container, string $id): bool
    {
        while (!interface_exists($id)) {
            if (!$container->hasAlias($id)) {
                return false;
            }

            $id = (string) $container->getAlias($id);
        }

        return true;
    }
}
