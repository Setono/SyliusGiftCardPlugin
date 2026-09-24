<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * A manual adjustment is the only way an admin moves a balance after issuance, so it has to leave a ledger row that
 * says by how much and why. This drives the form the admin fills in, from the rendered page to the persisted ledger
 */
final class AdjustGiftCardBalanceActionTest extends AdminFunctionalTestCase
{
    private const FORM = 'setono_sylius_gift_card_adjust_balance';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_shows_the_balance_the_adjustment_applies_to(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $response = $this->request('GET', $this->uri($giftCard));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['Current balance: $50.00'], self::textsOf($response, '//*[contains(@class, "info message")]'));
        self::assertCount(1, self::textsOf($response, sprintf('//input[@name="%s[amount]"]', self::FORM)));
        self::assertCount(1, self::textsOf($response, sprintf('//textarea[@name="%s[reason]"]', self::FORM)));
    }

    /** @test */
    public function it_adjusts_the_balance_and_records_the_reason_in_the_ledger(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        // The admin types major units, and a negative amount decreases the balance
        $response = $this->submit($giftCard, '-12.50', 'Customer returned part of the goods');

        self::assertTrue($response->isRedirect(sprintf('/admin/gift-cards/%d/edit', (int) $giftCard->getId())));

        $reloaded = $this->reloadGiftCard($giftCard);
        self::assertSame(3750, $reloaded->getAmount());
        self::assertSame(5000, $reloaded->getInitialAmount(), 'an adjustment moves the balance, not what the card was issued with');

        $transactions = $reloaded->getTransactions();
        self::assertCount(1, $transactions);

        $transaction = $transactions->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);
        self::assertSame(GiftCardTransactionInterface::TYPE_MANUAL, $transaction->getType());
        self::assertSame(-1250, $transaction->getAmount());
        self::assertSame('Customer returned part of the goods', $transaction->getReason());

        self::assertStringContainsString('The gift card balance was adjusted', (string) $this->followRedirect($response)->getContent());
    }

    /** @test */
    public function it_increases_the_balance_by_a_positive_amount(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $response = $this->submit($giftCard, '7.25', 'Goodwill');

        self::assertTrue($response->isRedirect());
        self::assertSame(5725, $this->reloadGiftCard($giftCard)->getAmount());
    }

    /**
     * An adjustment nobody can explain later is exactly what the ledger is there to prevent
     *
     * @test
     */
    public function it_refuses_an_adjustment_without_a_reason(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $response = $this->submit($giftCard, '10', '');

        self::assertSame(200, $response->getStatusCode(), 'the form is shown again with the error');
        $this->assertBalanceUntouched($giftCard, 5000);
    }

    /** @test */
    public function it_refuses_an_adjustment_of_nothing(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $response = $this->submit($giftCard, '0', 'Nothing really');

        self::assertSame(200, $response->getStatusCode());
        $this->assertBalanceUntouched($giftCard, 5000);
    }

    /** @test */
    public function it_refuses_a_submission_without_the_forms_csrf_token(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $response = $this->request('POST', $this->uri($giftCard), [self::FORM => [
            'amount' => '10',
            'reason' => 'Forged',
        ]]);

        self::assertSame(200, $response->getStatusCode());
        $this->assertBalanceUntouched($giftCard, 5000);
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $this->getChannel();

        self::assertSame(404, $this->request('GET', '/admin/gift-cards/987654/adjust-balance')->getStatusCode());
    }

    /** @test */
    public function it_is_only_available_to_a_signed_in_administrator(): void
    {
        $giftCard = $this->persistGiftCard('ADJUSTME', 5000);

        $this->logOut();

        $response = $this->request('GET', $this->uri($giftCard));

        self::assertTrue($response->isRedirect('http://localhost/admin/login'));
    }

    private function submit(GiftCardInterface $giftCard, string $amount, string $reason): Response
    {
        $form = $this->request('GET', $this->uri($giftCard));

        return $this->request('POST', $this->uri($giftCard), [self::FORM => [
            'amount' => $amount,
            'reason' => $reason,
            '_token' => self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM)),
        ]]);
    }

    private function assertBalanceUntouched(GiftCardInterface $giftCard, int $balance): void
    {
        $reloaded = $this->reloadGiftCard($giftCard);

        self::assertSame($balance, $reloaded->getAmount());
        self::assertCount(0, $reloaded->getTransactions());
    }

    private function uri(GiftCardInterface $giftCard): string
    {
        return sprintf('/admin/gift-cards/%d/adjust-balance', (int) $giftCard->getId());
    }
}
