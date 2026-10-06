<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardCodeGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * A code is read off a card and typed in, or read out over the phone, so it only uses characters that cannot
     * be mistaken for one another: no 0/O and no 1/I/L
     *
     * @test
     */
    public function it_generates_codes_of_the_configured_length_from_unambiguous_characters(): void
    {
        $generator = new GiftCardCodeGenerator($this->repositoryWithoutCodes(), 16);

        $codes = [];
        for ($i = 0; $i < 100; ++$i) {
            $code = $generator->generate();

            self::assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{16}$/', $code);
            $codes[] = $code;
        }

        self::assertCount(100, array_unique($codes), 'codes are drawn at random');
    }

    /**
     * Any length the configuration allows, from the floor every code is held to up to what the code column holds,
     * and one equal to a raised minimum
     *
     * @test
     */
    public function it_honours_the_configured_length(): void
    {
        self::assertSame(12, strlen((new GiftCardCodeGenerator($this->repositoryWithoutCodes(), 12))->generate()));
        self::assertSame(255, strlen((new GiftCardCodeGenerator($this->repositoryWithoutCodes(), 255))->generate()));
        self::assertSame(20, strlen((new GiftCardCodeGenerator($this->repositoryWithoutCodes(), 20, 20))->generate()));
    }

    /**
     * The configuration refuses these lengths, but not a code_length or minimum_code_length taken from an environment
     * variable, whose value is only known at runtime. The generator is built on every product page and for every
     * order, so it only refuses once it is asked for a code
     *
     * @dataProvider provideCodeLengthsTheConfigurationRefuses
     *
     * @test
     */
    public function it_refuses_to_generate_a_code_of_a_length_the_configuration_refuses(int $codeLength, int $minimumCodeLength, string $expectedMessage): void
    {
        $generator = new GiftCardCodeGenerator($this->repositoryWithoutCodes(), $codeLength, $minimumCodeLength);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $generator->generate();
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function provideCodeLengthsTheConfigurationRefuses(): iterable
    {
        yield 'guessable' => [11, 12, 'The code_length (11) must be between 12 and 255'];
        yield 'guessable, with a minimum below the floor too' => [8, 4, 'The code_length (8) must be between 12 and 255'];
        yield 'longer than the code column' => [256, 12, 'The code_length (256) must be between 12 and 255'];
        yield 'below the minimum' => [16, 20, 'The code_length (16) must be at least the minimum_code_length (20)'];
        yield 'one below the minimum' => [19, 20, 'The code_length (19) must be at least the minimum_code_length (20)'];
    }

    /**
     * A code identifies the card it is redeemed as, so one already given to a card is never handed out again
     *
     * @test
     */
    public function it_draws_again_until_the_code_is_not_taken(): void
    {
        /** @var list<string> $looked */
        $looked = [];

        $repository = $this->prophesize(GiftCardRepositoryInterface::class);
        $repository->findOneByCode(Argument::type('string'))->will(static function (array $arguments) use (&$looked): ?GiftCard {
            $code = $arguments[0];
            self::assertIsString($code);
            $looked[] = $code;

            // the first two codes drawn belong to existing cards
            return count($looked) < 3 ? new GiftCard() : null;
        });

        $code = (new GiftCardCodeGenerator($repository->reveal(), 16))->generate();

        self::assertCount(3, $looked);
        self::assertSame($looked[2], $code);
    }

    private function repositoryWithoutCodes(): GiftCardRepositoryInterface
    {
        $repository = $this->prophesize(GiftCardRepositoryInterface::class);
        $repository->findOneByCode(Argument::type('string'))->willReturn(null);

        return $repository->reveal();
    }
}
