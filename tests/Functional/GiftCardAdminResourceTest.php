<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Issuing and deleting gift cards through Sylius' resource routes, with what the plugin hooks into them: the
 * opening balance is recorded when a card is issued, along with the administrator who issued it, and a card whose
 * balance has moved cannot be deleted
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
        // Issuing a card hands out money as much as adjusting a balance does, so the administrator who did it is named.
        // No order paid for it
        self::assertSame('administrator', $transaction->getCreatedBy());
        self::assertNull($transaction->getOrder());
    }

    /**
     * The admin may write the code down to hand the card over, so the card must not be saved under another one
     *
     * @test
     */
    public function it_issues_the_gift_card_with_the_code_the_form_shows(): void
    {
        $form = $this->request('GET', '/admin/gift-cards/new');
        $shown = self::valueOf($form, sprintf('//input[@name="%s[code]"]', self::FORM));
        self::assertNotSame('', $shown);

        $response = $this->issue([
            'code' => $shown,
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
        ], $form);

        self::assertTrue($response->isRedirect());
        self::assertSame($shown, $this->findTheOnlyGiftCard()->getCode());
    }

    /**
     * The cart normalizes the code a customer types before looking it up, so a code stored the way the admin typed it
     * could never be redeemed
     *
     * @test
     */
    public function it_stores_a_typed_code_the_way_customers_enter_it(): void
    {
        $response = $this->issue([
            'code' => 'summer-2026 xyz',
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
        ]);

        self::assertTrue($response->isRedirect());
        self::assertSame('SUMMER2026XYZ', $this->findTheOnlyGiftCard()->getCode());
    }

    /**
     * The code is unique, and it is the normalized code that has to be: typed differently, it is still the same code
     *
     * @test
     */
    public function it_refuses_a_code_another_gift_card_already_has(): void
    {
        $this->persistGiftCard('SUMMER2026XYZ', 5000);

        $response = $this->issue([
            'code' => 'summer-2026 xyz',
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['Code must be unique'], self::codeErrors($response));

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        self::assertCount(1, $repository->findAll());
    }

    /** @test */
    public function it_refuses_a_code_with_nothing_left_once_normalized(): void
    {
        $response = $this->issue([
            'code' => ' -- ',
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['Please enter code'], self::codeErrors($response));
    }

    /**
     * A code is a bearer token, so a code an admin types is held to minimum_code_length like a generated one. It is the
     * normalized code that counts: 14 characters as typed, 11 once the dashes are dropped
     *
     * @test
     */
    public function it_refuses_a_typed_code_shorter_than_the_minimum(): void
    {
        $response = $this->issue([
            'code' => 'abcd-efgh-jkm',
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['The code must have at least 12 letters and digits, so it cannot be guessed.'], self::codeErrors($response));

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        self::assertSame([], $repository->findAll());
    }

    /**
     * Cards brought over from 0.12 may have codes shorter than the minimum. The customer was given that code, and the
     * card is validated whenever it is edited, so the minimum must not lock the admin out of it
     *
     * @test
     */
    public function it_lets_the_admin_edit_a_card_whose_code_is_shorter_than_the_minimum(): void
    {
        $giftCard = $this->persistGiftCard('OLDCODE', 5000);
        $uri = sprintf('/admin/gift-cards/%d/edit', (int) $giftCard->getId());

        $form = $this->request('GET', $uri);
        self::assertSame(200, $form->getStatusCode());

        $response = $this->request('POST', $uri, [
            '_method' => 'PUT',
            self::FORM => [
                'enabled' => '1',
                'customMessage' => 'Happy birthday',
                '_token' => self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM)),
            ],
        ]);

        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect after saving, got a %d response', $response->getStatusCode()));

        $giftCard = $this->reloadGiftCard($giftCard);
        self::assertSame('OLDCODE', $giftCard->getCode());
        self::assertSame('Happy birthday', $giftCard->getCustomMessage());
    }

    /**
     * The browser submits every line break of the message as CR LF, while the textarea counts it as one character, so
     * a message at the limit by the browser's count must not be refused as too long
     *
     * @test
     */
    public function it_counts_a_line_break_in_the_message_as_one_character_the_way_the_browser_does(): void
    {
        // 195 characters on 6 lines: the configured limit of 200 by the browser's count, 205 as submitted
        $lines = str_split(str_repeat('a', 195), 33);

        $response = $this->issue([
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
            'customMessage' => implode("\r\n", $lines),
        ]);

        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect after saving, got a %d response', $response->getStatusCode()));
        self::assertSame(implode("\n", $lines), $this->findTheOnlyGiftCard()->getCustomMessage());
    }

    /**
     * The design decides what the card's PDF looks like, and a physical card is one the shop hands over or posts
     * itself, so both are chosen when the card is issued
     *
     * @test
     */
    public function it_issues_a_gift_card_with_the_design_and_delivery_type_the_admin_picks(): void
    {
        $this->persistDesign('birthday', 'Birthday', $this->getChannel());

        $form = $this->request('GET', '/admin/gift-cards/new');
        // A card is virtual and without a design until the admin picks otherwise
        self::assertSame('virtual', self::valueOf($form, sprintf('//select[@name="%s[deliveryType]"]/option[@selected]', self::FORM)));
        self::assertSame('', self::valueOf($form, sprintf('//input[@type="radio"][@name="%s[design]"][@checked]', self::FORM)));
        self::assertSame('birthday', self::valueOf($form, sprintf('//input[@type="radio"][@name="%s[design]"][@value="birthday"]', self::FORM)));

        $response = $this->issue([
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
            'deliveryType' => 'physical',
            'design' => 'birthday',
        ], $form);

        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect after saving, got a %d response', $response->getStatusCode()));

        $giftCard = $this->findTheOnlyGiftCard();
        self::assertSame(GiftCardDeliveryType::Physical, $giftCard->getDeliveryType());
        self::assertSame('birthday', $giftCard->getDesign()?->getCode());
    }

    /**
     * The channel is chosen on the same form, so the picker offers the designs of every channel, and the one the
     * chosen channel does not offer has to be refused like any other invalid value rather than saved or crash
     *
     * @test
     */
    public function it_refuses_a_design_the_chosen_channel_does_not_offer(): void
    {
        $this->persistDesign('outlet_only', 'Outlet only', $this->createChannel('OUTLET'));

        $form = $this->request('GET', '/admin/gift-cards/new');
        // offered, telling the picker which channel it belongs to
        $choice = sprintf('//input[@type="radio"][@name="%s[design]"][@value="outlet_only"]', self::FORM);
        self::assertSame('outlet_only', self::valueOf($form, $choice));
        self::assertSame(['Outlet only'], self::textsOf($form, '//label[@data-channels="OUTLET"]'));

        $response = $this->issue([
            'channel' => 'TEST_CHANNEL',
            'currencyCode' => 'USD',
            'amount' => '10',
            'design' => 'outlet_only',
        ], $form);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ["The design Outlet only is not available in the channel Test channel. Choose one of the channel's designs, or none."],
            self::designErrors($response),
        );

        /** @var GiftCardRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        self::assertSame([], $repository->findAll());
    }

    /**
     * The design only decides what the card's PDF looks like, so it can be changed once the card is issued. The
     * delivery type is settled at issuance: the edit form shows it, but takes no new one
     *
     * @test
     */
    public function it_lets_the_admin_change_the_design_of_an_issued_card_but_not_its_delivery_type(): void
    {
        $classic = $this->persistDesign('classic', 'Classic', $this->getChannel());
        $this->persistDesign('birthday', 'Birthday', $this->getChannel());

        $giftCard = $this->persistGiftCard('ISSUEDCARD12345', 5000);
        $giftCard->setDesign($classic);
        $this->manager->flush();

        $uri = sprintf('/admin/gift-cards/%d/edit', (int) $giftCard->getId());
        $form = $this->request('GET', $uri);
        self::assertSame('classic', self::valueOf($form, sprintf('//input[@type="radio"][@name="%s[design]"][@checked]', self::FORM)));
        self::assertCount(1, self::textsOf($form, sprintf('//select[@name="%s[deliveryType]"][@disabled]', self::FORM)));

        $response = $this->request('POST', $uri, [
            '_method' => 'PUT',
            self::FORM => [
                'enabled' => '1',
                'design' => 'birthday',
                'deliveryType' => 'physical',
                '_token' => self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM)),
            ],
        ]);

        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect after saving, got a %d response', $response->getStatusCode()));

        $giftCard = $this->reloadGiftCard($giftCard);
        self::assertSame('birthday', $giftCard->getDesign()?->getCode());
        self::assertSame(GiftCardDeliveryType::Virtual, $giftCard->getDeliveryType());
    }

    /**
     * A card keeps printing with the design it was issued with after the design is disabled, so editing the card must
     * neither drop the design nor refuse to save it
     *
     * @test
     */
    public function it_keeps_the_design_of_an_issued_card_after_the_design_is_disabled(): void
    {
        $retired = $this->persistDesign('retired', 'Retired', $this->getChannel());

        $giftCard = $this->persistGiftCard('ISSUEDCARD12345', 5000);
        $giftCard->setDesign($retired);
        $retired->disable();
        $this->manager->flush();

        $uri = sprintf('/admin/gift-cards/%d/edit', (int) $giftCard->getId());
        $form = $this->request('GET', $uri);
        self::assertSame('retired', self::valueOf($form, sprintf('//input[@type="radio"][@name="%s[design]"][@checked]', self::FORM)));

        $response = $this->request('POST', $uri, [
            '_method' => 'PUT',
            self::FORM => [
                'enabled' => '1',
                'design' => 'retired',
                'customMessage' => 'Happy birthday',
                '_token' => self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM)),
            ],
        ]);

        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect after saving, got a %d response', $response->getStatusCode()));

        $giftCard = $this->reloadGiftCard($giftCard);
        self::assertSame('retired', $giftCard->getDesign()?->getCode());
        self::assertSame('Happy birthday', $giftCard->getCustomMessage());
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
     * ledger with it. The grid no longer offers the button then, so this presses it on an index opened before the card
     * was spent from
     *
     * @test
     */
    public function it_refuses_to_delete_a_gift_card_whose_balance_has_moved(): void
    {
        $giftCard = $this->persistGiftCard('PARTLYSPENT', 5000);
        $token = $this->deleteToken($giftCard);

        $giftCard = $this->reloadGiftCard($giftCard);
        $giftCard->setAmount(3000);
        $this->manager->flush();

        $response = $this->delete($giftCard, $token);

        self::assertTrue($response->isRedirect());
        self::assertStringContainsString('The gift card cannot be removed.', (string) $this->followRedirect($response)->getContent());
        self::assertSame(3000, $this->reloadGiftCard($giftCard)->getAmount());
    }

    /**
     * Submits the create form the way a browser does: without a code among the fields, the code the form proposes is
     * sent along
     *
     * @param array<string, string> $fields
     * @param Response|null $form the create form already requested, to submit that one rather than a new one
     */
    private function issue(array $fields, ?Response $form = null): Response
    {
        $form ??= $this->request('GET', '/admin/gift-cards/new');
        self::assertSame(200, $form->getStatusCode());

        $fields['_token'] = self::valueOf($form, sprintf('//input[@name="%s[_token]"]', self::FORM));
        $fields['code'] ??= self::valueOf($form, sprintf('//input[@name="%s[code]"]', self::FORM));

        return $this->request('POST', '/admin/gift-cards/new', [self::FORM => $fields]);
    }

    /**
     * @return list<string> the validation errors the page shows on the code field
     */
    private static function codeErrors(Response $response): array
    {
        return self::textsOf($response, sprintf(
            '//div[contains(concat(" ", normalize-space(@class), " "), " field ")][.//input[@name="%s[code]"]]//*[contains(@class, "sylius-validation-error")]',
            self::FORM,
        ));
    }

    /**
     * @return list<string> the validation errors the page shows on the design picker
     */
    private static function designErrors(Response $response): array
    {
        return self::textsOf($response, sprintf(
            '//fieldset[.//input[@name="%s[design]"]]//*[contains(@class, "sylius-validation-error")]',
            self::FORM,
        ));
    }

    private function persistDesign(string $code, string $name, ChannelInterface $channel): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setName($name);
        $design->setEnabled(true);
        $design->addChannel($channel);

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }

    /**
     * Gift cards cannot be deleted in bulk: Sylius' bulk delete stops at the first card it may not delete, after
     * deleting the ones before it, so no route is registered for it
     *
     * @test
     */
    public function it_registers_no_bulk_delete_route_for_gift_cards_or_designs(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertNull($router->getRouteCollection()->get('setono_sylius_gift_card_admin_gift_card_bulk_delete'));
        self::assertNull($router->getRouteCollection()->get('setono_sylius_gift_card_admin_gift_card_design_bulk_delete'));
        self::assertNotNull($router->getRouteCollection()->get('setono_sylius_gift_card_admin_gift_card_delete'));
    }

    /**
     * Presses the delete button of the card's row in the gift cards index, or submits the token of a button pressed on
     * an index opened before
     */
    private function delete(GiftCardInterface $giftCard, ?string $token = null): Response
    {
        return $this->request('POST', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()), [
            '_method' => 'DELETE',
            '_csrf_token' => $token ?? $this->deleteToken($giftCard),
        ]);
    }

    /**
     * The CSRF token of the delete button in the card's row of the gift cards index
     */
    private function deleteToken(GiftCardInterface $giftCard): string
    {
        $index = $this->request('GET', '/admin/gift-cards/');
        $form = sprintf('//form[@action="/admin/gift-cards/%d"][input[@name="_method"][@value="DELETE"]]', (int) $giftCard->getId());

        return self::valueOf($index, $form . '//input[@name="_csrf_token"]');
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
