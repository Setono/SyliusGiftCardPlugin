<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

/**
 * Codes are stored in canonical form while customers meet them grouped by dashes on the card, so every lookup
 * by code normalizes what it is given. The database collation happens to forgive the case on its own; the
 * separators are what would otherwise make the printed code miss
 */
final class GiftCardRepositoryTest extends GiftCardFunctionalTestCase
{
    private const CODE = 'NORMALIZE0000001';

    private GiftCardRepositoryInterface $giftCardRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $this->giftCardRepository = $giftCardRepository;
    }

    /**
     * @test
     *
     * @dataProvider renderingsOfTheCode
     */
    public function it_finds_a_gift_card_by_any_rendering_of_its_code(string $input): void
    {
        $this->createGiftCard();

        self::assertSame(self::CODE, $this->giftCardRepository->findOneByCode($input)?->getCode());
        self::assertSame(
            self::CODE,
            $this->giftCardRepository->findOneEnabledByCodeAndChannel($input, $this->getChannel())?->getCode(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function renderingsOfTheCode(): iterable
    {
        yield 'the stored code' => ['NORMALIZE0000001'];
        yield 'as printed on the card, in lower case' => ['norm-aliz-e000-0001'];
        yield 'grouped by spaces, with surrounding whitespace' => [' NORM ALIZ E000 0001 '];
    }

    /** @test */
    public function it_does_not_match_a_gift_card_with_a_different_code(): void
    {
        $this->createGiftCard();

        self::assertNull($this->giftCardRepository->findOneByCode('NORM-ALIZ-E000-0002'));
    }

    private function createGiftCard(): void
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode(self::CODE);
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();
        // so the lookups below hit the database rather than the identity map
        $this->manager->clear();
    }
}
