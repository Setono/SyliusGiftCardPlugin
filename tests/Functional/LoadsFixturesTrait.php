<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Sylius\Bundle\FixturesBundle\Fixture\FixtureRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * @mixin KernelTestCase
 */
trait LoadsFixturesTrait
{
    /**
     * Loads a fixture the way sylius:fixtures:load does: the fixture is looked up by its name, the options of the
     * suite are validated and normalised by the fixture's configuration tree, and the result is handed to the fixture
     *
     * @param array<string, mixed> $options the options as they would appear under the fixture in a suite
     */
    private function loadFixture(string $name, array $options): void
    {
        /** @var FixtureRegistryInterface $registry */
        $registry = self::getContainer()->get('sylius_fixtures.fixture_registry');
        $fixture = $registry->getFixture($name);

        $fixture->load((new Processor())->processConfiguration($fixture, [$options]));
    }
}
