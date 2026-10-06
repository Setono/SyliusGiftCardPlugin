<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Fixture\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Fixture\Factory\GiftCardExampleFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * What the factory makes is covered by tests/Functional/GiftCardFixtureTest.php, against the test application
 */
final class GiftCardExampleFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * The configuration refuses these minimums, but not a minimum_code_length taken from an environment variable,
     * whose value is only known at runtime. A fixture is then refused rather than held to it
     *
     * @dataProvider provideMinimumCodeLengthsTheConfigurationRefuses
     *
     * @test
     */
    public function it_refuses_to_hold_a_code_to_a_minimum_the_configuration_refuses(int $minimumCodeLength, string $expectedMessage): void
    {
        $factory = new GiftCardExampleFactory(
            $this->prophesize(GiftCardRepositoryInterface::class)->reveal(),
            $this->prophesize(FactoryInterface::class)->reveal(),
            $this->prophesize(GiftCardCodeGeneratorInterface::class)->reveal(),
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
            $this->prophesize(RepositoryInterface::class)->reveal(),
            $this->prophesize(GiftCardBalanceOperatorInterface::class)->reveal(),
            $minimumCodeLength,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $factory->create(['code' => 'ABCDEFGHJKMNPQRS']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideMinimumCodeLengthsTheConfigurationRefuses(): iterable
    {
        yield 'guessable' => [11, 'The minimum_code_length (11) must be between 12 and 255'];
        yield 'longer than the code column' => [256, 'The minimum_code_length (256) must be between 12 and 255'];
    }
}
