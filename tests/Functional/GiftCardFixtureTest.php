<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Fixture\Factory\GiftCardExampleFactory;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Currency\Model\Currency;
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
        $euro = new Currency();
        $euro->setCode('EUR');
        $this->manager->persist($euro);
        $this->getChannel()->addCurrency($euro);
        $this->manager->flush();

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

    /** @test */
    public function it_rejects_a_currency_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency XYZ was not found. Use one of: USD');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR01', 'currency' => 'XYZ']]]);
    }

    /** @test */
    public function it_rejects_a_currency_the_channel_does_not_sell_in(): void
    {
        $euro = new Currency();
        $euro->setCode('EUR');
        $this->manager->persist($euro);
        $this->manager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EUR/');

        $this->loadFixture('setono_gift_card', ['custom' => [['code' => 'FIXTUREBADCUR02', 'currency' => 'EUR']]]);
    }

    /** @test */
    public function it_rejects_a_channel_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

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

    private function findGiftCard(string $code): GiftCardInterface
    {
        $this->manager->clear();

        $giftCard = $this->repository->findOneByCode($code);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard, sprintf('the fixture should have created gift card %s', $code));

        return $giftCard;
    }
}
