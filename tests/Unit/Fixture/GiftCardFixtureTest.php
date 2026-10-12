<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Fixture;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Fixture\GiftCardFixture;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The configuration tree only: what the fixture loads is covered by tests/Functional/GiftCardFixtureTest.php
 */
final class GiftCardFixtureTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_takes_entries_whose_codes_differ_once_normalized(): void
    {
        $entries = [
            'printed' => ['code' => 'abcd-efgh-jkmn-pqrs'],
            'plastic' => ['code' => 'abcd-efgh-jkmn-pqrt'],
            'generated' => ['amount' => 50.0],
            'also_generated' => ['amount' => 50.0],
        ];

        self::assertSame($entries, $this->process($entries)['custom']);
    }

    /**
     * Two entries giving one code would be one card, so the list is refused, naming both entries
     *
     * @test
     */
    public function it_refuses_two_entries_whose_codes_are_the_same_once_normalized(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "setono_gift_card.custom": The entries "printed" and "plastic" give the same gift card code once it is normalized ("abcd-efgh-jkmn-pqrs" and "ABCD EFGH JKMN PQRS" are both ABCDEFGHJKMNPQRS). Each card needs a code of its own');

        $this->process([
            'printed' => ['code' => 'abcd-efgh-jkmn-pqrs'],
            'generated' => ['amount' => 50.0],
            'plastic' => ['code' => 'ABCD EFGH JKMN PQRS'],
        ]);
    }

    /**
     * @param array<array-key, array<string, mixed>> $entries
     *
     * @return array<array-key, mixed> the options as the configuration tree leaves them
     */
    private function process(array $entries): array
    {
        $fixture = new GiftCardFixture(
            $this->prophesize(ObjectManager::class)->reveal(),
            $this->prophesize(ExampleFactoryInterface::class)->reveal(),
            new GiftCardCodeNormalizer(),
        );

        return (new Processor())->processConfiguration($fixture, [['custom' => $entries]]);
    }
}
