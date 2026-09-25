<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The plugin configures the gift card and design grids itself. These request the indexes the way the admin uses them,
 * with the filters and columns the grids are configured with
 */
final class AdminGridTest extends AdminFunctionalTestCase
{
    private const ROWS = '//tbody[@data-test-grid-table-body]/tr';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_filters_gift_cards_by_a_part_of_their_code(): void
    {
        $this->persistGiftCard('SPRINGSALE01', 5000);
        $this->persistGiftCard('SPRINGSALE02', 5000);
        $this->persistGiftCard('WINTER01', 5000);

        $response = $this->request('GET', '/admin/gift-cards/', ['criteria' => [
            'code' => ['type' => 'contains', 'value' => 'SPRING'],
        ]]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['SPRI-NGSA-LE01', 'SPRI-NGSA-LE02'], $this->codes($response));
    }

    /**
     * The card, the emails and the show page print a code grouped (ABCD-EFGH-JKMN-PQRS), and that is how a customer
     * reads it out to support, so the filter has to find the card however the grouping and the case are typed
     *
     * @test
     *
     * @dataProvider codesAsTyped
     *
     * @param list<string> $expected
     */
    public function it_filters_gift_cards_by_their_code_typed_the_way_it_is_printed(string $type, string $typed, array $expected): void
    {
        $this->persistGiftCard('ABCDEFGHJKMNPQRS', 5000);
        $this->persistGiftCard('WXYZ23456789WXYZ', 5000);

        $response = $this->request('GET', '/admin/gift-cards/', ['criteria' => [
            'code' => ['type' => $type, 'value' => $typed],
        ]]);

        self::assertSame($expected, $this->codes($response));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function codesAsTyped(): iterable
    {
        yield 'contains, as printed' => ['contains', 'ABCD-EFGH-JKMN-PQRS', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'contains, grouped by spaces in lower case' => ['contains', 'abcd efgh jkmn pqrs', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'contains, a fragment across two groups' => ['contains', 'fgh-jk', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'equal, as printed in lower case' => ['equal', 'abcd-efgh-jkmn-pqrs', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'not equal, as printed' => ['not_equal', 'ABCD-EFGH-JKMN-PQRS', ['WXYZ-2345-6789-WXYZ']];
        yield 'starts with, the first two groups' => ['starts_with', 'abcd-efgh', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'ends with, the last group' => ['ends_with', '-pqrs', ['ABCD-EFGH-JKMN-PQRS']];
        yield 'in, a list of printed codes' => ['in', 'abcd-efgh-jkmn-pqrs, WXYZ 2345 6789 WXYZ', ['ABCD-EFGH-JKMN-PQRS', 'WXYZ-2345-6789-WXYZ']];
        yield 'not in, a list of one printed code' => ['not_in', 'ABCD-EFGH-JKMN-PQRS', ['WXYZ-2345-6789-WXYZ']];
    }

    /**
     * Typing nothing but separators is like typing nothing, not a search for an empty code
     *
     * @test
     */
    public function it_lists_every_gift_card_when_nothing_of_a_code_is_typed(): void
    {
        $this->persistGiftCard('ABCDEFGHJKMNPQRS', 5000);
        $this->persistGiftCard('WXYZ23456789WXYZ', 5000);

        $response = $this->request('GET', '/admin/gift-cards/', ['criteria' => [
            'code' => ['type' => 'contains', 'value' => ' - '],
        ]]);

        self::assertSame(['ABCD-EFGH-JKMN-PQRS', 'WXYZ-2345-6789-WXYZ'], $this->codes($response));
    }

    /**
     * Every other page shows a code grouped for reading, so the grid must not show the same card differently
     *
     * @test
     */
    public function it_shows_the_codes_grouped_like_the_show_page(): void
    {
        $giftCard = $this->persistGiftCard('ABCDEFGHJKMNPQRS', 5000);

        $index = $this->request('GET', '/admin/gift-cards/');
        $show = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));

        self::assertSame(['ABCD-EFGH-JKMN-PQRS'], self::textsOf($index, self::ROWS . '/td[1]'));
        self::assertContains('ABCD-EFGH-JKMN-PQRS', self::textsOf($show, '//table//tr/td[2]'));
    }

    /** @test */
    public function it_filters_gift_cards_by_whether_they_are_enabled(): void
    {
        $this->persistGiftCard('ENABLED01', 5000);
        $this->persistGiftCard('DISABLED01', 5000, false);

        self::assertSame(['ENAB-LED0-1'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['enabled' => 'true']])));
        self::assertSame(['DISA-BLED-01'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['enabled' => 'false']])));
    }

    /**
     * The balance column only mentions what the card was issued with once the two differ
     *
     * @test
     */
    public function it_shows_the_initial_amount_next_to_a_balance_that_has_moved(): void
    {
        $this->persistGiftCard('UNTOUCHED', 5000);
        $spent = $this->persistGiftCard('PARTLYSPENT', 5000);
        $spent->setAmount(1000);
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame(['$50.00'], self::textsOf($response, self::ROWS . '[normalize-space(td[1])="UNTO-UCHE-D"]/td[3]'));
        self::assertSame(['$10.00 Initial amount: $50.00'], self::textsOf($response, self::ROWS . '[normalize-space(td[1])="PART-LYSP-ENT"]/td[3]'));
    }

    /** @test */
    public function it_lists_gift_card_designs_by_position_with_their_name(): void
    {
        $this->persistDesign('second', 'Birthday', 2);
        $this->persistDesign('first', 'Christmas', 1);

        $response = $this->request('GET', '/admin/gift-card-designs/');

        self::assertSame(200, $response->getStatusCode());
        // code, name
        self::assertSame(['first', 'second'], self::textsOf($response, self::ROWS . '/td[2]'));
        self::assertSame(['Christmas', 'Birthday'], self::textsOf($response, self::ROWS . '/td[3]'));
    }

    /**
     * @return list<string> the codes in the grid, sorted
     */
    private function codes(Response $response): array
    {
        self::assertSame(200, $response->getStatusCode());

        $codes = self::textsOf($response, self::ROWS . '/td[1]');
        sort($codes);

        return $codes;
    }

    private function persistDesign(string $code, string $name, int $position): void
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName($name);
        $design->setPosition($position);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();
    }
}
