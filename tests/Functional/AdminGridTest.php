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
        self::assertSame(['SPRINGSALE01', 'SPRINGSALE02'], $this->codes($response));
    }

    /** @test */
    public function it_filters_gift_cards_by_whether_they_are_enabled(): void
    {
        $this->persistGiftCard('ENABLED01', 5000);
        $this->persistGiftCard('DISABLED01', 5000, false);

        self::assertSame(['ENABLED01'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['enabled' => 'true']])));
        self::assertSame(['DISABLED01'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['enabled' => 'false']])));
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

        self::assertSame(['$50.00'], self::textsOf($response, self::ROWS . '[td[1]="UNTOUCHED"]/td[3]'));
        self::assertSame(['$10.00 Initial amount: $50.00'], self::textsOf($response, self::ROWS . '[td[1]="PARTLYSPENT"]/td[3]'));
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
