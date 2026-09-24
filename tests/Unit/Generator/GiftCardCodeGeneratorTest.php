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

    /** @test */
    public function it_honours_a_shorter_configured_length(): void
    {
        self::assertSame(6, strlen((new GiftCardCodeGenerator($this->repositoryWithoutCodes(), 6))->generate()));
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
