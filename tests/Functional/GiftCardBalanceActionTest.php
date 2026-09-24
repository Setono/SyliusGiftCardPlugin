<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

/**
 * The outstanding balance report is the liability a merchant carries for issued gift cards, so each currency gets a
 * row of its own: amounts in different currencies cannot be added up
 */
final class GiftCardBalanceActionTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_reports_the_count_total_and_average_per_currency(): void
    {
        $this->persistGiftCard('USDONE', 5000);
        $this->persistGiftCard('USDTWO', 2501);
        $euro = $this->persistGiftCard('EURONE', 1234);
        $euro->setCurrencyCode('EUR');
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/balance');

        self::assertSame(200, $response->getStatusCode());
        // 7501 / 2 is rounded down to whole minor units
        self::assertSame(['USD', '2', '$75.01', '$37.50'], $this->row($response, 'USD'));
        self::assertSame(['EUR', '1', '€12.34', '€12.34'], $this->row($response, 'EUR'));
    }

    /** @test */
    public function it_says_so_when_there_is_no_outstanding_balance(): void
    {
        $this->getChannel();

        $response = $this->request('GET', '/admin/gift-cards/balance');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['No results'], self::textsOf($response, '//tbody/tr/td'));
    }

    /**
     * @return list<string> the cells of the row for the currency
     */
    private function row(Response $response, string $currencyCode): array
    {
        return self::textsOf($response, sprintf('//tbody/tr[td[1][normalize-space()="%s"]]/td', $currencyCode));
    }
}
