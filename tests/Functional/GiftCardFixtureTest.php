<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Fixture\Factory\GiftCardExampleFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The setono_gift_card fixture seeds the demo shop and the end to end suite, so it is loaded here the way
 * sylius:fixtures:load loads it, and what lands in the database is checked
 */
final class GiftCardFixtureTest extends GiftCardFunctionalTestCase
{
    use LoadsFixturesTrait;

    private GiftCardRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $this->repository = $repository;

        $this->getChannel();
    }

    /**
     * Amounts in a fixture file are written the way people read them, in major units, and stored in minor units
     * like every other amount in Sylius
     *
     * @test
     */
    public function it_loads_gift_cards_with_the_given_options(): void
    {
        $this->loadFixture('setono_gift_card', ['custom' => [
            ['code' => 'FIXTURE00000001', 'channel' => 'TEST_CHANNEL', 'currency' => 'USD', 'amount' => 25.5, 'enabled' => false],
            ['code' => 'FIXTURE00000002', 'amount' => 50],
        ]]);

        $first = $this->findGiftCard('FIXTURE00000001');
        self::assertSame('TEST_CHANNEL', $first->getChannel()?->getCode());
        self::assertSame('USD', $first->getCurrencyCode());
        self::assertSame(2550, $first->getAmount());
        self::assertSame(2550, $first->getInitialAmount());
        self::assertFalse($first->isEnabled());
        self::assertSame(GiftCardDeliveryType::Virtual, $first->getDeliveryType());

        $second = $this->findGiftCard('FIXTURE00000002');
        self::assertSame(5000, $second->getAmount());
        self::assertTrue($second->isEnabled(), 'a seeded card is enabled unless the fixture says otherwise');
    }

    /**
     * Seeded cards go through the balance operator like every other card, so the ledger of the demo data accounts
     * for the balance on it
     *
     * @test
     */
    public function it_records_the_issuance_of_the_gift_cards_it_loads(): void
    {
        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTURELEDGER01', 'amount' => 75]]]);

        $transactions = $this->findGiftCard('FIXTURELEDGER01')->getTransactions();
        self::assertCount(1, $transactions);

        $issue = $transactions->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $issue);
        self::assertSame(GiftCardTransactionInterface::TYPE_ISSUE, $issue->getType());
        self::assertSame(7500, $issue->getAmount());
    }

    /**
     * Orders are kept in the base currency of their channel, so that is the only currency a card can be spent in,
     * even in a channel that also displays prices in other currencies
     *
     * @test
     */
    public function it_seeds_random_gift_cards_in_the_base_currency_of_a_channel(): void
    {
        $this->createCurrency('EUR');

        $this->loadFixture('setono_gift_card', ['random' => 3]);

        $giftCards = $this->repository->findAll();
        self::assertCount(3, $giftCards);

        $codes = [];
        foreach ($giftCards as $giftCard) {
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            $codes[] = (string) $giftCard->getCode();

            self::assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{16}$/', (string) $giftCard->getCode(), 'a code from the code generator');
            self::assertSame('TEST_CHANNEL', $giftCard->getChannel()?->getCode());
            self::assertSame('USD', $giftCard->getCurrencyCode());
            self::assertContains(
                $giftCard->getAmount(),
                [1000, 2000, 3000, 4000, 5000, 7500, 10000, 15000, 20000, 25000, 30000, 40000, 50000],
            );
            self::assertSame($giftCard->getAmount(), $giftCard->getInitialAmount());
            self::assertTrue($giftCard->isEnabled());
            self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
            self::assertCount(1, $giftCard->getTransactions());
        }

        self::assertCount(3, array_unique($codes));
    }

    /**
     * Fixtures may be loaded into a database that already holds them, so a code that exists updates that card
     * instead of failing on the unique code
     *
     * @test
     */
    public function it_updates_the_gift_card_that_already_has_the_code(): void
    {
        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTURERELOAD01', 'amount' => 10]]]);
        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTURERELOAD01', 'amount' => 20, 'enabled' => false]]]);

        self::assertCount(1, $this->repository->findAll());

        $giftCard = $this->findGiftCard('FIXTURERELOAD01');
        self::assertSame(2000, $giftCard->getAmount());
        self::assertFalse($giftCard->isEnabled());
        self::assertCount(1, $giftCard->getTransactions(), 'issuance is recorded once however often the fixture runs');
    }

    /**
     * The fixture tree does not expose the delivery type, but the example factory takes it, as a value or as the
     * enum, for applications building on it
     *
     * @test
     */
    public function its_example_factory_creates_physical_gift_cards(): void
    {
        /** @var GiftCardExampleFactory $factory */
        $factory = self::getContainer()->get(GiftCardExampleFactory::class);

        self::assertSame(
            GiftCardDeliveryType::Physical,
            $factory->create(['code' => 'PHYSICALVALUE01', 'deliveryType' => 'physical'])->getDeliveryType(),
        );
        self::assertSame(
            GiftCardDeliveryType::Physical,
            $factory->create(['code' => 'PHYSICALENUM001', 'deliveryType' => GiftCardDeliveryType::Physical])->getDeliveryType(),
        );
    }

    /**
     * A fixture file may still name the currency, as long as it names the base currency the card would be issued in
     * anyway
     *
     * @test
     */
    public function it_loads_gift_cards_in_the_base_currency_of_a_channel_that_offers_other_currencies(): void
    {
        $this->createCurrency('EUR');

        $this->loadFixture('setono_gift_card', ['custom' => [
            ['code' => 'FIXTUREBASECUR1', 'channel' => 'TEST_CHANNEL', 'currency' => 'USD'],
            ['code' => 'FIXTUREBASECUR2', 'channel' => 'TEST_CHANNEL'],
        ]]);

        self::assertSame('USD', $this->findGiftCard('FIXTUREBASECUR1')->getCurrencyCode());
        self::assertSame('USD', $this->findGiftCard('FIXTUREBASECUR2')->getCurrencyCode());
    }

    /**
     * A card is issued in the base currency of its channel, so an entry that names its currency but no channel goes to
     * a channel with that base currency. Any channel was picked before, so the same file loaded or failed depending on
     * the pick; with twenty such entries, a pick that ignores the currency cannot get them all right by chance
     *
     * @test
     */
    public function it_issues_a_card_naming_only_its_currency_on_a_channel_with_that_base_currency(): void
    {
        $this->createChannelWithBaseCurrency('EURO_CHANNEL', 'EUR');

        $entries = [];
        for ($i = 1; $i <= 10; ++$i) {
            $entries[] = ['code' => sprintf('FIXTUREEURO%04d', $i), 'currency' => 'EUR'];
            $entries[] = ['code' => sprintf('FIXTUREUSD%05d', $i), 'currency' => 'USD'];
        }

        $this->loadFixture('setono_gift_card', ['custom' => $entries]);

        for ($i = 1; $i <= 10; ++$i) {
            $euroCard = $this->findGiftCard(sprintf('FIXTUREEURO%04d', $i));
            self::assertSame('EURO_CHANNEL', $euroCard->getChannel()?->getCode());
            self::assertSame('EUR', $euroCard->getCurrencyCode());

            $dollarCard = $this->findGiftCard(sprintf('FIXTUREUSD%05d', $i));
            self::assertSame('TEST_CHANNEL', $dollarCard->getChannel()?->getCode());
            self::assertSame('USD', $dollarCard->getCurrencyCode());
        }
    }

    /**
     * Applications building on the example factory may hand it the currency itself rather than its code
     *
     * @test
     */
    public function its_example_factory_issues_a_card_given_only_a_currency_on_a_channel_with_that_base_currency(): void
    {
        $euroChannel = $this->createChannelWithBaseCurrency('EURO_CHANNEL', 'EUR');

        /** @var GiftCardExampleFactory $factory */
        $factory = self::getContainer()->get(GiftCardExampleFactory::class);

        for ($i = 1; $i <= 20; ++$i) {
            $giftCard = $factory->create(['code' => sprintf('FACTORYEURO%04d', $i), 'currency' => $euroChannel->getBaseCurrency()]);

            self::assertSame($euroChannel, $giftCard->getChannel());
            self::assertSame('EUR', $giftCard->getCurrencyCode());
        }
    }

    /**
     * An entry that names its currency but no channel cannot be issued when no channel has that currency as its base
     * currency, and the message says which currency that is, whichever channels the shop has
     *
     * @test
     */
    public function it_rejects_a_currency_that_is_no_channels_base_currency_when_no_channel_is_named(): void
    {
        $this->createChannelWithBaseCurrency('EURO_CHANNEL', 'EUR');
        // Offered by the test channel, but the base currency of none
        $this->createCurrency('GBP');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gift cards are issued in the base currency of their channel, and no channel has GBP as its base currency');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR05', 'currency' => 'GBP']]]);
    }

    /**
     * Naming the channel decides it: an entry is held to the base currency of the channel it names, and is not moved to
     * another channel that has the currency it names
     *
     * @test
     */
    public function it_keeps_an_entry_on_the_channel_it_names(): void
    {
        $this->createChannelWithBaseCurrency('EURO_CHANNEL', 'EUR');

        $this->loadFixture('setono_gift_card', ['custom' => [
            ['code' => 'FIXTURENAMED001', 'channel' => 'EURO_CHANNEL', 'currency' => 'EUR'],
            ['code' => 'FIXTURENAMED002', 'channel' => 'EURO_CHANNEL'],
            ['code' => 'FIXTURENAMED003', 'channel' => 'TEST_CHANNEL', 'currency' => 'USD'],
        ]]);

        $first = $this->findGiftCard('FIXTURENAMED001');
        self::assertSame('EURO_CHANNEL', $first->getChannel()?->getCode());
        self::assertSame('EUR', $first->getCurrencyCode());

        $second = $this->findGiftCard('FIXTURENAMED002');
        self::assertSame('EURO_CHANNEL', $second->getChannel()?->getCode());
        self::assertSame('EUR', $second->getCurrencyCode());

        $third = $this->findGiftCard('FIXTURENAMED003');
        self::assertSame('TEST_CHANNEL', $third->getChannel()?->getCode());
        self::assertSame('USD', $third->getCurrencyCode());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Gift cards are issued in the channel's base currency (USD for channel TEST_CHANNEL), got: EUR");

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTURENAMED004', 'channel' => 'TEST_CHANNEL', 'currency' => 'EUR']]]);
    }

    /**
     * Orders are kept in the base currency of their channel, so a card in another currency the channel offers could
     * never be redeemed. The admin refuses to issue one, and so does the fixture
     *
     * @test
     */
    public function it_rejects_a_currency_the_channel_offers_that_is_not_its_base_currency(): void
    {
        $this->createCurrency('EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Gift cards are issued in the channel's base currency (USD for channel TEST_CHANNEL), got: EUR");

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR03', 'channel' => 'TEST_CHANNEL', 'currency' => 'EUR']]]);
    }

    /**
     * Applications building on the example factory may hand it the currency itself rather than its code
     *
     * @test
     */
    public function its_example_factory_rejects_a_currency_that_is_not_the_base_currency_of_the_channel(): void
    {
        $euro = $this->createCurrency('EUR');

        /** @var GiftCardExampleFactory $factory */
        $factory = self::getContainer()->get(GiftCardExampleFactory::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Gift cards are issued in the channel's base currency (USD for channel TEST_CHANNEL), got: EUR");

        $factory->create(['code' => 'FIXTUREBADCUR04', 'channel' => 'TEST_CHANNEL', 'currency' => $euro]);
    }

    /**
     * A short code is a guessable one, and demo data has a way of ending up in production, so the fixture holds a code
     * to minimum_code_length like the admin does
     *
     * @test
     */
    public function it_rejects_a_code_shorter_than_the_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A gift card code must have at least 12 characters, so it cannot be guessed, got: "SHORTCODE01"');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'SHORTCODE01', 'amount' => 10]]]);
    }

    /** @test */
    public function it_rejects_a_currency_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency XYZ was not found. Gift cards are issued in the base currency of their channel');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR01', 'currency' => 'XYZ']]]);
    }

    /** @test */
    public function it_rejects_a_currency_the_channel_does_not_sell_in(): void
    {
        $this->createCurrency('EUR', false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Gift cards are issued in the channel's base currency (USD for channel TEST_CHANNEL), got: EUR");

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR02', 'channel' => 'TEST_CHANNEL', 'currency' => 'EUR']]]);
    }

    /** @test */
    public function it_rejects_a_channel_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel UNKNOWN_CHANNEL was not found');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCHAN1', 'channel' => 'UNKNOWN_CHANNEL']]]);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidGiftCardOptions
     *
     * @param array<string, mixed> $options
     */
    public function it_rejects_invalid_options(array $options): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadFixture('setono_gift_card', ['custom' => [$options]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidGiftCardOptions(): iterable
    {
        yield 'an empty code' => [['code' => '']];
        yield 'an amount that is not a number' => [['amount' => 'fifty']];
        yield 'enabled that is not a boolean' => [['enabled' => 'yes']];
        yield 'an unknown option' => [['colour' => 'gold']];
    }

    /**
     * Creates a currency, which the test channel offers besides its base currency USD unless told otherwise
     */
    private function createCurrency(string $code, bool $offeredByTheChannel = true): CurrencyInterface
    {
        $currency = new Currency();
        $currency->setCode($code);
        $this->manager->persist($currency);

        if ($offeredByTheChannel) {
            $this->getChannel()->addCurrency($currency);
        }

        $this->manager->flush();

        return $currency;
    }

    /**
     * Creates another channel whose base currency is a new currency, which the test channel does not offer. The new
     * channel offers the test channel's USD as well
     */
    private function createChannelWithBaseCurrency(string $code, string $currencyCode): ChannelInterface
    {
        $currency = $this->createCurrency($currencyCode, false);

        $channel = $this->createChannel($code);
        $channel->setBaseCurrency($currency);
        $channel->addCurrency($currency);
        $this->manager->flush();

        return $channel;
    }

    private function findGiftCard(string $code): GiftCardInterface
    {
        $this->manager->clear();

        $giftCard = $this->repository->findOneByCode($code);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard, sprintf('the fixture should have created gift card %s', $code));

        return $giftCard;
    }
}
