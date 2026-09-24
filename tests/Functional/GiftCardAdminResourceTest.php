<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issuing and deleting gift cards through Sylius' resource routes, with what the plugin hooks into them: the
 * opening balance is recorded when a card is issued, and a card whose balance has moved cannot be deleted
 */
final class GiftCardAdminResourceTest extends AdminFunctionalTestCase
{
    private const FORM = 'setono_sylius_gift_card_gift_card';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel();
        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_records_the_opening_balance_of_a_gift_card_issued_in_the_admin(): void
    {
        $response = $this->issue([
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '25.50',
            'enabled' => '1',
            'expiresAt' => '2031-03-15',
        ]);

        self::assertTrue($response->isRedirect());
        self::assertStringStartsWith('/admin/gift-cards/', (string) $response->headers->get('Location'));

        $giftCard = $this->findTheOnlyGiftCard();
        self::assertSame(2550, $giftCard->getAmount());
        self::assertSame(2550, $giftCard->getInitialAmount());
        // Valid through the end of the day the admin picked
        self::assertSame('2031-03-15 23:59:59', $giftCard->getExpiresAt()?->format('Y-m-d H:i:s'));

        $transactions = $giftCard->getTransactions();
        self::assertCount(1, $transactions);

        $transaction = $transactions->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);
        self::assertSame(GiftCardTransactionInterface::TYPE_ISSUE, $transaction->getType());
        self::assertSame(2550, $transaction->getAmount());
    }

    /** @test */
    public function it_issues_nothing_when_the_form_is_invalid(): void
    {
        $response = $this->issue([
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '',
        ]);

        self::assertSame(422, $response->getStatusCode());

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        self::assertSame([], $repository->findAll());
    }

    /** @test */
    public function it_deletes_a_gift_card_that_was_never_used(): void
    {
        $giftCard = $this->persistGiftCard('UNUSED', 5000);
        $id = $giftCard->getId();

        $response = $this->delete($giftCard);

        self::assertTrue($response->isRedirect('/admin/gift-cards/'));

        $this->manager->clear();
        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        self::assertNull($repository->find($id));
    }

    /**
     * Once some of the balance has been spent the card is part of the shop's history, and deleting it would take its
     * ledger with it
     *
     * @test
     */
    public function it_refuses_to_delete_a_gift_card_whose_balance_has_moved(): void
    {
        $giftCard = $this->persistGiftCard('PARTLYSPENT', 5000);
        $giftCard->setAmount(3000);
        $this->manager->flush();

        $response = $this->delete($giftCard);

        self::assertTrue($response->isRedirect());
        self::assertStringContainsString('The gift card cannot be removed.', (string) $this->followRedirect($response)->getContent());
        self::assertSame(3000, $this->reloadGiftCard($giftCard)->getAmount());
    }

    /**
     * The code is not among the fields: it is generated when the card is created, and the form only shows it
     *
     * @param array<string, string> $fields
     */
    private function issue(array $fields): Response
    {
        $form = $this->request('GET', '/admin/gift-cards/new');
        self::assertSame(200, $form->getStatusCode());

        $fields['_token'] = self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM));

        return $this->request('POST', '/admin/gift-cards/new', [self::FORM => $fields]);
    }

    /**
     * Presses the delete button of the card's row in the gift cards index
     */
    private function delete(GiftCardInterface $giftCard): Response
    {
        $uri = sprintf('/admin/gift-cards/%d', (int) $giftCard->getId());

        $index = $this->request('GET', '/admin/gift-cards/');
        $form = sprintf('//form[@action="%s"][input[@name="_method"][@value="DELETE"]]', $uri);

        return $this->request('POST', $uri, [
            '_method' => 'DELETE',
            '_csrf_token' => self::valueOf($index, $form . '//input[@name="_csrf_token"]'),
        ]);
    }

    private function findTheOnlyGiftCard(): GiftCardInterface
    {
        $this->manager->clear();

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $giftCards = $repository->findAll();
        self::assertCount(1, $giftCards);

        $giftCard = reset($giftCards);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        return $giftCard;
    }
}
