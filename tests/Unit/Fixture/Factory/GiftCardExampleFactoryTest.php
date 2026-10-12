<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Fixture\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Fixture\Factory\GiftCardExampleFactory;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * Most of what the factory makes is covered by tests/Functional/GiftCardFixtureTest.php, against the test application
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
            $this->prophesize(GiftCardCurrencyProviderInterface::class)->reveal(),
            $this->prophesize(GiftCardCodeNormalizerInterface::class)->reveal(),
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

    /**
     * The cart normalizes the code a customer types before it looks the card up, so the card is stored, and looked up
     * for an update, by the normalized code (#490)
     *
     * @dataProvider provideCodesWrittenAnyWay
     *
     * @test
     */
    public function it_stores_the_code_normalized(string $code): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMNPQRS'])->willReturn(null)->shouldBeCalledOnce();

        $giftCard = $this->createFactory($giftCardRepository->reveal())->create(['code' => $code, 'channel' => $this->createChannel(), 'amount' => 10]);

        self::assertSame('ABCDEFGHJKMNPQRS', $giftCard->getCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCodesWrittenAnyWay(): iterable
    {
        yield 'normalized' => ['ABCDEFGHJKMNPQRS'];
        yield 'in lowercase' => ['abcdefghjkmnpqrs'];
        yield 'grouped with dashes' => ['ABCD-EFGH-JKMN-PQRS'];
        yield 'grouped with spaces, in mixed case' => ['abcd EFGH jkmn PQRS'];
    }

    /**
     * Fixtures may be loaded into a database that already holds them, and a code written another way is still the
     * code of the card that is there
     *
     * @test
     */
    public function it_updates_the_gift_card_whose_code_the_code_normalizes_to(): void
    {
        $existing = new GiftCard();
        $existing->setCode('ABCDEFGHJKMNPQRS');

        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMNPQRS'])->willReturn($existing);

        $giftCard = $this->createFactory($giftCardRepository->reveal())->create(['code' => 'abcd-efgh-jkmn-pqrs', 'channel' => $this->createChannel(), 'amount' => 20]);

        self::assertSame($existing, $giftCard);
        self::assertSame('ABCDEFGHJKMNPQRS', $giftCard->getCode());
        self::assertSame(2000, $giftCard->getAmount());
    }

    /**
     * Only the letters and digits count towards the minimum, since those are all that is stored
     *
     * @test
     */
    public function it_takes_a_code_with_as_many_letters_and_digits_as_the_minimum(): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMN'])->willReturn(null);

        $giftCard = $this->createFactory($giftCardRepository->reveal())->create(['code' => 'abcd-efgh-jkmn', 'channel' => $this->createChannel(), 'amount' => 10]);

        self::assertSame('ABCDEFGHJKMN', $giftCard->getCode());
    }

    /**
     * The message quotes the code the way it was written, which is what can be found in the fixture file, and what was
     * counted
     *
     * @test
     */
    public function it_refuses_a_code_with_fewer_letters_and_digits_than_the_minimum_however_long_it_is_written(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A gift card code must have at least 12 characters once it is normalized, so it cannot be guessed, got: "ab-cd-ef-gh-jk", normalized to "ABCDEFGHJK"');

        $this->createFactory($this->prophesize(GiftCardRepositoryInterface::class)->reveal())->create(['code' => 'ab-cd-ef-gh-jk']);
    }

    /** @test */
    public function it_creates_a_gift_card_from_the_options_and_records_its_issuance(): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMNPQRS'])->willReturn(null);
        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $channel = $this->createChannel();

        $giftCard = $this->createFactory($giftCardRepository->reveal(), $balanceOperator->reveal())->create([
            'code' => 'ABCDEFGHJKMNPQRS',
            'channel' => $channel,
            'amount' => 25.5,
            'enabled' => false,
            'delivery_type' => 'physical',
        ]);

        self::assertSame($channel, $giftCard->getChannel());
        self::assertSame('USD', $giftCard->getCurrencyCode());
        self::assertSame(2550, $giftCard->getAmount());
        self::assertSame(2550, $giftCard->getInitialAmount());
        self::assertFalse($giftCard->isEnabled());
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
        $balanceOperator->issue($giftCard)->shouldHaveBeenCalledOnce();
    }

    /**
     * An amount is written in major units and stored in minor units, rounded to the nearest: 19.99 is 1998.9999... cents
     * and 0.07 is 7.0000...1 in floating point
     *
     * @dataProvider provideAmounts
     *
     * @test
     */
    public function it_stores_an_amount_in_minor_units(float $amount, int $expected): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMNPQRS'])->willReturn(null);

        $giftCard = $this->createFactory($giftCardRepository->reveal())->create(['code' => 'ABCDEFGHJKMNPQRS', 'channel' => $this->createChannel(), 'amount' => $amount]);

        self::assertSame($expected, $giftCard->getAmount());
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function provideAmounts(): iterable
    {
        yield 'rounded up' => [19.99, 1999];
        yield 'rounded down' => [0.07, 7];
        yield 'the most the balance column holds' => [21474836.47, 2147483647];
        yield 'the least the balance column holds' => [-21474836.48, -2147483648];
    }

    /**
     * The balance is kept in a signed 32-bit integer column, so an amount beyond it is refused rather than left to the
     * database
     *
     * @dataProvider provideAmountsTheBalanceColumnCannotHold
     *
     * @test
     */
    public function it_refuses_an_amount_the_balance_column_cannot_hold(float $amount): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A gift card amount has to fit the integer column its balance is kept in');

        $this->createFactory($this->prophesize(GiftCardRepositoryInterface::class)->reveal())->create(['code' => 'ABCDEFGHJKMNPQRS', 'channel' => $this->createChannel(), 'amount' => $amount]);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideAmountsTheBalanceColumnCannotHold(): iterable
    {
        yield 'a cent above it' => [21474836.48];
        yield 'a cent below it' => [-21474836.49];
    }

    /** @test */
    public function it_issues_an_enabled_virtual_gift_card_unless_told_otherwise(): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findOneBy(['code' => 'ABCDEFGHJKMNPQRS'])->willReturn(null);

        $giftCard = $this->createFactory($giftCardRepository->reveal())->create(['code' => 'ABCDEFGHJKMNPQRS', 'channel' => $this->createChannel(), 'amount' => 10]);

        self::assertTrue($giftCard->isEnabled());
        self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
    }

    private function createFactory(
        GiftCardRepositoryInterface $giftCardRepository,
        ?GiftCardBalanceOperatorInterface $balanceOperator = null,
    ): GiftCardExampleFactory {
        $giftCardFactory = $this->prophesize(FactoryInterface::class);
        $giftCardFactory->createNew()->will(static fn (): GiftCardInterface => new GiftCard());

        return new GiftCardExampleFactory(
            $giftCardRepository,
            $giftCardFactory->reveal(),
            $this->prophesize(GiftCardCodeGeneratorInterface::class)->reveal(),
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
            $this->prophesize(RepositoryInterface::class)->reveal(),
            $balanceOperator ?? $this->prophesize(GiftCardBalanceOperatorInterface::class)->reveal(),
            $this->prophesize(GiftCardCurrencyProviderInterface::class)->reveal(),
            new GiftCardCodeNormalizer(),
        );
    }

    private function createChannel(): Channel
    {
        $currency = new Currency();
        $currency->setCode('USD');

        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setBaseCurrency($currency);

        return $channel;
    }
}
