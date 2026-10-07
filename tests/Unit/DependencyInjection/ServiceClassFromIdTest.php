<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;

/**
 * Symfony's ResolveClassPass gives a definition without a class the class its id names, when the id is a fully
 * qualified class name. So a service whose id is its class leaves out the class attribute, and the class is written
 * once: renaming the class means renaming the id, with no second copy to forget. The class attribute is only for
 * services whose id is something else, such as a dotted id
 */
final class ServiceClassFromIdTest extends TestCase
{
    private const SERVICES_NAMESPACE = 'http://symfony.com/schema/dic/services';

    /** @test */
    public function it_leaves_out_the_class_of_a_service_whose_id_is_its_class(): void
    {
        $definitions = 0;
        $repeating = [];
        foreach (self::serviceFiles() as $name => $document) {
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('s', self::SERVICES_NAMESPACE);

            $services = $xpath->query('//s:service[@id]');
            self::assertInstanceOf(\DOMNodeList::class, $services);

            foreach ($services as $service) {
                self::assertInstanceOf(\DOMElement::class, $service);
                ++$definitions;

                if ($service->getAttribute('class') === $service->getAttribute('id')) {
                    $repeating[] = sprintf('%s: %s repeats its id as its class', $name, $service->getAttribute('id'));
                }
            }
        }

        self::assertGreaterThan(0, $definitions, 'No service definitions were found');
        self::assertSame([], $repeating, 'Leave out class="..." where it is the id');
    }

    /**
     * Every service container file under src/Resources/config, found on disk so a new one is checked too
     *
     * @return array<string, \DOMDocument> each file's path relative to src/Resources/config => its document
     */
    private static function serviceFiles(): array
    {
        $directory = dirname(__DIR__, 3) . '/src/Resources/config';

        $documents = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('xml' !== $file->getExtension()) {
                continue;
            }

            $document = new \DOMDocument();
            self::assertTrue($document->load($file->getPathname()), sprintf('%s is not valid XML', $file->getPathname()));

            if (self::SERVICES_NAMESPACE === $document->documentElement?->namespaceURI) {
                $documents[substr($file->getPathname(), strlen($directory) + 1)] = $document;
            }
        }

        self::assertArrayHasKey('services.xml', $documents);
        ksort($documents);

        return $documents;
    }
}
