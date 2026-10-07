<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use Doctrine\Common\EventArgs;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\DependencyInjection\SetonoSyliusGiftCardExtension;
use Sylius\Bundle\CoreBundle\EventListener\ImagesUploadListener;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber or listener only runs once it is registered as a service with the tag that hooks it up, and nothing
 * else notices when that service goes missing: its own unit test builds it by hand, and the functional suite fails
 * only where a test happens to depend on its event. Without its service, SendGiftCardEmailSubscriber would quietly
 * stop emailing the gift cards an admin creates. So what has to be registered is worked out from the code (the
 * classes in src/, the resources) rather than read from the service files
 */
final class EventSubscriberWiringTest extends TestCase
{
    private const PLUGIN_NAMESPACE = 'Setono\\SyliusGiftCardPlugin\\';

    /** @test */
    public function it_registers_every_event_subscriber_as_a_service_tagged_kernel_event_subscriber(): void
    {
        $tagged = self::classesTagged(self::container(), 'kernel.event_subscriber');

        $unregistered = [];
        foreach (self::pluginClasses() as $class) {
            if (is_subclass_of($class, EventSubscriberInterface::class) && !in_array($class, $tagged, true)) {
                $unregistered[] = $class;
            }
        }

        self::assertSame([], $unregistered, 'These subscribers have no service tagged kernel.event_subscriber, so they never run');
    }

    /**
     * Symfony's RegisterListenersPass refuses such a service too, but only once a kernel boots and compiles the container
     *
     * @test
     */
    public function it_gives_the_event_subscriber_tag_to_event_subscribers_only(): void
    {
        $tagged = self::classesTagged(self::container(), 'kernel.event_subscriber');
        self::assertNotEmpty($tagged);

        foreach ($tagged as $class) {
            self::assertTrue(
                is_subclass_of($class, EventSubscriberInterface::class),
                sprintf('%s is tagged kernel.event_subscriber, but does not implement %s', $class, EventSubscriberInterface::class),
            );
        }
    }

    /**
     * Doctrine has no interface for its listeners: it calls the listener's method named after the event, handing it
     * the event's arguments. So a public method taking Doctrine's EventArgs handles the event it is named after, and
     * its class has to be registered for that event (doctrine.event_listener), just as a registration has to name an
     * event its class handles
     *
     * @test
     */
    public function it_registers_every_doctrine_listener_for_exactly_the_events_it_handles(): void
    {
        $handled = [];
        foreach (self::pluginClasses() as $class) {
            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $type = ($method->getParameters()[0] ?? null)?->getType();
                if ($type instanceof \ReflectionNamedType && is_a($type->getName(), EventArgs::class, true)) {
                    $handled[] = sprintf('%s::%s()', $class, $method->getName());
                }
            }
        }

        $registered = [];
        $container = self::container();
        foreach ($container->findTaggedServiceIds('doctrine.event_listener') as $id => $tags) {
            // only the plugin's own classes are scanned for handlers above
            $class = self::classOf($container, $id);
            if (!str_starts_with($class, self::PLUGIN_NAMESPACE)) {
                continue;
            }

            foreach ($tags as $tag) {
                self::assertIsArray($tag);
                $event = $tag['event'] ?? null;
                self::assertIsString($event, sprintf('The %s service has a doctrine.event_listener tag without an event', $id));

                $registered[] = sprintf('%s::%s()', $class, $event);
            }
        }

        sort($handled);
        sort($registered);

        self::assertSame($handled, $registered, 'Each Doctrine event handler in src/ should be registered for its event, and only those');
    }

    /**
     * Sylius' ImagesUploadListener uploads a resource's images on its pre_create and pre_update events, but Sylius only
     * registers it for its own resources (products, taxons), so the plugin registers it for each of its resources with
     * images. Without it, the images an admin adds to a design are never uploaded
     *
     * @test
     */
    public function it_uploads_the_images_of_every_resource_that_has_them(): void
    {
        $container = self::container();

        $expected = [];
        $resources = $container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        foreach ($resources as $alias => $resource) {
            self::assertIsArray($resource);
            self::assertIsArray($resource['classes'] ?? null);
            $model = $resource['classes']['model'] ?? null;
            self::assertIsString($model);

            if (is_a($model, ImagesAwareInterface::class, true)) {
                $expected[] = sprintf('%s.pre_create -> uploadImages()', $alias);
                $expected[] = sprintf('%s.pre_update -> uploadImages()', $alias);
            }
        }

        $registered = [];
        foreach ($container->findTaggedServiceIds('kernel.event_listener') as $id => $tags) {
            if (!is_a(self::classOf($container, $id), ImagesUploadListener::class, true)) {
                continue;
            }

            foreach ($tags as $tag) {
                self::assertIsArray($tag);
                self::assertIsString($tag['event'] ?? null);
                self::assertIsString($tag['method'] ?? null);

                $registered[] = sprintf('%s -> %s()', $tag['event'], $tag['method']);
            }
        }

        sort($expected);
        sort($registered);

        self::assertSame($expected, $registered, sprintf('%s should upload the images of each resource with images, on its pre_create and pre_update', ImagesUploadListener::class));
    }

    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusGiftCardExtension())->load([], $container);

        return $container;
    }

    /**
     * @return list<string> the class of each service carrying the given tag
     */
    private static function classesTagged(ContainerBuilder $container, string $tag): array
    {
        return array_map(
            static fn (string $id): string => self::classOf($container, $id),
            array_keys($container->findTaggedServiceIds($tag)),
        );
    }

    private static function classOf(ContainerBuilder $container, string $id): string
    {
        return $container->getDefinition($id)->getClass() ?? $id;
    }

    /**
     * Every class in src/, found on disk, so a class no service file mentions is found as well
     *
     * @return list<class-string>
     */
    private static function pluginClasses(): array
    {
        $src = dirname(__DIR__, 3) . '/src';

        /** @var iterable<string, \SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

        $classes = [];
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $class = self::PLUGIN_NAMESPACE . str_replace(
                \DIRECTORY_SEPARATOR,
                '\\',
                substr($file->getPathname(), strlen($src) + 1, -strlen('.php')),
            );

            // An abstract class is never a service itself, so a base subscriber or listener needs no registration
            if (class_exists($class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        self::assertNotEmpty($classes);

        return $classes;
    }
}
