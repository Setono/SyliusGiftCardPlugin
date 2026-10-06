<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Currency\Model\Currency;
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

    /**
     * One status per card in place of the enabled flag, which said nothing about an expired or spent card
     *
     * @test
     */
    public function it_shows_the_status_of_every_card(): void
    {
        $this->persistGiftCard('USABLE01', 5000);
        $this->persistGiftCard('DISABLED01', 5000, false);
        $this->persistGiftCard('SPENT01', 0);
        $this->persistGiftCard('EXPIRED01', 5000)->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertSame([
            'DISA-BLED-01' => 'Disabled',
            'EXPI-RED0-1' => 'Expired',
            'SPEN-T01' => 'Spent',
            'USAB-LE01' => 'Usable',
        ], $this->statuses($response));
        self::assertNotContains('Enabled', self::textsOf($response, '//table/thead//th'));
    }

    /**
     * A card waiting in somebody's cart is not a liability yet and most of them are never bought, so the grid leaves
     * them out until the admin asks for them. Everything else stays: cards issued in the admin (enabled or not), cards
     * that were paid for, and a paid card that was disabled afterwards, which its ledger tells apart from a pending one
     *
     * @test
     */
    public function it_hides_the_pending_cards_unless_asked_for_them(): void
    {
        $this->persistGiftCard('ADMINENABLED', 5000);
        $this->persistGiftCard('ADMINDISABLED', 5000, false);
        $this->persistBoughtGiftCard('PENDING', enabled: false);
        $this->persistBoughtGiftCard('PAID', enabled: true);
        $cancelled = $this->persistBoughtGiftCard('CANCELLED', enabled: true);
        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = self::getContainer()->get(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($cancelled);
        $cancelled->disable();
        $this->manager->flush();

        $everythingButPending = ['ADMI-NDIS-ABLE-D', 'ADMI-NENA-BLED', 'CANC-ELLE-D', 'PAID'];

        // opened without criteria, and filtered with the pending filter left at hiding them
        self::assertSame($everythingButPending, $this->codes($this->request('GET', '/admin/gift-cards/')));
        self::assertSame($everythingButPending, $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => '']])));

        $shown = $this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => 'show']]);
        self::assertSame([...$everythingButPending, 'PEND-ING'], $this->codes($shown));
        self::assertSame('Pending', $this->statuses($shown)['PEND-ING']);
        self::assertSame('Disabled', $this->statuses($shown)['CANC-ELLE-D']);

        self::assertSame(['PEND-ING'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => 'only']])));
    }

    /**
     * A bought card is only issued once its order is paid, so the card of an order cancelled before then (as
     * sylius:cancel-unpaid-orders cancels every expired one) is still disabled, on its unit and without a ledger row.
     * It waits for nothing any more, so it is listed as disabled with the other cards and is no pending card
     *
     * @test
     */
    public function it_lists_the_card_of_an_order_cancelled_before_payment_as_disabled_rather_than_pending(): void
    {
        $this->persistBoughtGiftCard('WAITING', enabled: false);
        $cancelled = $this->persistBoughtGiftCard('CANCELLEDUNPAID', enabled: false);
        $cancelled->getOrder()?->setState(OrderInterface::STATE_CANCELLED);
        $this->manager->flush();

        $index = $this->request('GET', '/admin/gift-cards/');
        self::assertSame(['CANC-ELLE-DUNP-AID' => 'Disabled'], $this->statuses($index));

        self::assertSame(['WAIT-ING'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => 'only']])));
        self::assertSame(
            ['CANC-ELLE-DUNP-AID' => 'Disabled', 'WAIT-ING' => 'Pending'],
            $this->statuses($this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => 'show']])),
        );
    }

    /**
     * The form shows the choice that is applied, so a grid opened without criteria does not claim to list them all
     *
     * @test
     */
    public function it_offers_hiding_the_pending_cards_as_the_choice_the_grid_opens_with(): void
    {
        $this->persistGiftCard('ANYCARD01', 5000);

        $options = '//select[@name="criteria[pending]"]/option';

        // Nothing is selected, so the browser shows the first choice, the empty one that hides them
        $response = $this->request('GET', '/admin/gift-cards/');
        self::assertSame(['Hide', 'Show', 'Show only these'], self::textsOf($response, $options));
        self::assertSame('', self::valueOf($response, $options . '[1]'));
        self::assertSame([], self::textsOf($response, $options . '[@selected]'));

        $shown = $this->request('GET', '/admin/gift-cards/', ['criteria' => ['pending' => 'show']]);
        self::assertSame(['Show'], self::textsOf($shown, $options . '[@selected]'));
    }

    /** @test */
    public function it_filters_gift_cards_by_whether_they_have_expired(): void
    {
        $this->persistGiftCard('EXPIRED01', 5000)->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->persistGiftCard('EXPIRESLATER', 5000)->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->persistGiftCard('NEVEREXPIRES', 5000)->setExpiresAt(null);
        // expired is about the date alone, so a disabled card past its date is expired too
        $disabled = $this->persistGiftCard('EXPIREDOFF', 5000, false);
        $disabled->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->manager->flush();

        self::assertSame(['EXPI-RED0-1', 'EXPI-REDO-FF'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['expired' => 'true']])));
        self::assertSame(['EXPI-RESL-ATER', 'NEVE-REXP-IRES'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['expired' => 'false']])));
    }

    /** @test */
    public function it_filters_gift_cards_by_whether_they_are_spent(): void
    {
        $this->persistGiftCard('SPENT01', 0);
        $this->persistGiftCard('SPENTOFF', 0, false);
        $partly = $this->persistGiftCard('PARTLYSPENT', 5000);
        $partly->setAmount(1);
        $this->persistGiftCard('UNTOUCHED', 5000);
        $this->manager->flush();

        self::assertSame(['SPEN-T01', 'SPEN-TOFF'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['spent' => 'true']])));
        self::assertSame(['PART-LYSP-ENT', 'UNTO-UCHE-D'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['spent' => 'false']])));
    }

    /**
     * Support is handed an email, or part of it, in whatever case the customer typed it
     *
     * @test
     */
    public function it_filters_gift_cards_by_a_part_of_their_customers_email(): void
    {
        $this->persistGiftCard('ANNASCARD', 5000)->setCustomer($this->persistCustomer('anna.jensen@example.com'));
        $this->persistGiftCard('BOBSCARD', 5000)->setCustomer($this->persistCustomer('bob@example.org'));
        $this->persistGiftCard('NOBODYSCARD', 5000);
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/', ['criteria' => ['customer' => ['value' => 'Anna.J']]]);

        self::assertSame(['ANNA-SCAR-D'], $this->codes($response));
        // only a part of the email is asked for, so there is nothing to choose a comparison from
        self::assertCount(0, self::textsOf($response, '//select[@name="criteria[customer][type]"]'));
        self::assertCount(1, self::textsOf($response, '//input[@name="criteria[customer][value]"]'));
    }

    /** @test */
    public function it_filters_gift_cards_by_channel_currency_and_delivery_type(): void
    {
        $euro = new Currency();
        $euro->setCode('EUR');
        $this->manager->persist($euro);

        $this->persistGiftCard('TESTCHANNEL01', 5000);
        $other = $this->persistGiftCard('OTHERCHANNEL01', 5000);
        $other->setChannel($this->createChannel('OTHER_CHANNEL'));
        $this->persistGiftCard('INEUROS01', 5000)->setCurrencyCode('EUR');
        $this->persistGiftCard('PHYSICAL01', 5000)->setDeliveryType(GiftCardDeliveryType::Physical);
        $this->manager->flush();

        self::assertSame(['OTHE-RCHA-NNEL-01'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => [
            'channel' => (string) $other->getChannel()?->getId(),
        ]])));
        self::assertSame(['INEU-ROS0-1'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['currencyCode' => 'EUR']])));
        self::assertSame(['PHYS-ICAL-01'], $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['deliveryType' => 'physical']])));
        self::assertCount(3, $this->codes($this->request('GET', '/admin/gift-cards/', ['criteria' => ['deliveryType' => 'virtual']])));
    }

    /**
     * Both ends of the range are whole days, and the day the range ends on is part of it
     *
     * @test
     */
    public function it_filters_gift_cards_by_the_day_they_were_created(): void
    {
        $this->persistGiftCard('JANUARY14', 5000)->setCreatedAt(new \DateTime('2026-01-14 23:00'));
        $this->persistGiftCard('JANUARY15', 5000)->setCreatedAt(new \DateTime('2026-01-15 00:00'));
        $this->persistGiftCard('JANUARY31', 5000)->setCreatedAt(new \DateTime('2026-01-31 18:00'));
        $this->persistGiftCard('FEBRUARY01', 5000)->setCreatedAt(new \DateTime('2026-02-01 00:30'));
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/', ['criteria' => ['createdAt' => [
            'from' => ['date' => '2026-01-15'],
            'to' => ['date' => '2026-01-31'],
        ]]]);

        self::assertSame(['JANU-ARY1-5', 'JANU-ARY3-1'], $this->codes($response));
    }

    /** @test */
    public function it_sorts_gift_cards_by_their_balance(): void
    {
        $this->persistGiftCard('MIDDLE01', 5000);
        $this->persistGiftCard('LOWEST01', 100);
        $this->persistGiftCard('HIGHEST01', 90000);

        $ascending = $this->request('GET', '/admin/gift-cards/', ['sorting' => ['amount' => 'asc']]);
        self::assertSame(['LOWE-ST01', 'MIDD-LE01', 'HIGH-EST0-1'], self::textsOf($ascending, self::ROWS . '/td[1]'));

        $descending = $this->request('GET', '/admin/gift-cards/', ['sorting' => ['amount' => 'desc']]);
        self::assertSame(['HIGH-EST0-1', 'MIDD-LE01', 'LOWE-ST01'], self::textsOf($descending, self::ROWS . '/td[1]'));
    }

    /**
     * Sorting by customer must not drop the cards that have none, as an inner join to the customer would
     *
     * @test
     */
    public function it_sorts_gift_cards_by_their_customers_email_keeping_those_without_one(): void
    {
        $this->persistGiftCard('CAROLSCARD', 5000)->setCustomer($this->persistCustomer('carol@example.com'));
        $this->persistGiftCard('ALICESCARD', 5000)->setCustomer($this->persistCustomer('alice@example.com'));
        $this->persistGiftCard('NOBODYSCARD', 5000);
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/', ['sorting' => ['customer' => 'desc']]);

        $codes = self::textsOf($response, self::ROWS . '/td[1]');
        self::assertCount(3, $codes);
        self::assertSame(['CARO-LSCA-RD', 'ALIC-ESCA-RD'], array_values(array_diff($codes, ['NOBO-DYSC-ARD'])));
    }

    /**
     * A card that was bought or whose balance has moved cannot be deleted, so its row does not offer to
     *
     * @test
     */
    public function it_only_offers_to_delete_a_card_that_may_be_deleted(): void
    {
        $untouched = $this->persistGiftCard('UNTOUCHED', 5000);
        $spent = $this->persistGiftCard('PARTLYSPENT', 5000);
        $spent->setAmount(1000);
        $paid = $this->persistBoughtGiftCard('PAID', enabled: true);
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-cards/');

        self::assertCount(1, $this->deleteForms($response, $untouched));
        self::assertCount(0, $this->deleteForms($response, $spent));
        self::assertCount(0, $this->deleteForms($response, $paid));
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
     * The name shown is the translation in the admin's locale, and that is what the grid sorts by
     *
     * @test
     */
    public function it_sorts_gift_card_designs_by_their_name(): void
    {
        $this->persistDesign('christmas', 'Christmas', 1);
        $this->persistDesign('anniversary', 'Anniversary', 2);
        $this->persistDesign('birthday', 'Birthday', 3);

        $ascending = $this->request('GET', '/admin/gift-card-designs/', ['sorting' => ['name' => 'asc']]);
        self::assertSame(200, $ascending->getStatusCode());
        self::assertSame(['Anniversary', 'Birthday', 'Christmas'], self::textsOf($ascending, self::ROWS . '/td[3]'));

        $descending = $this->request('GET', '/admin/gift-card-designs/', ['sorting' => ['name' => 'desc']]);
        self::assertSame(['Christmas', 'Birthday', 'Anniversary'], self::textsOf($descending, self::ROWS . '/td[3]'));
    }

    /**
     * A design is only offered in the channels it is enabled for, so the grid says which those are
     *
     * @test
     */
    public function it_lists_the_channels_of_every_design(): void
    {
        $this->persistDesign('everywhere', 'Everywhere', 1)->addChannel($this->createChannel('SECOND_CHANNEL'));
        $this->persistDesign('nowhere', 'Nowhere', 2)->removeChannel($this->getChannel());
        $this->manager->flush();

        $response = $this->request('GET', '/admin/gift-card-designs/');

        self::assertSame(['Channels'], self::textsOf($response, '//table/thead//th[4]'));
        self::assertSame(['Test channel SECOND_CHANNEL', ''], self::textsOf($response, self::ROWS . '/td[4]'));
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

    /**
     * @return array<string, string> the status of every card in the grid by its code as listed, sorted by code
     */
    private function statuses(Response $response): array
    {
        self::assertSame(200, $response->getStatusCode());

        $statuses = array_combine(self::textsOf($response, self::ROWS . '/td[1]'), self::textsOf($response, self::ROWS . '/td[5]'));
        ksort($statuses);

        return $statuses;
    }

    /**
     * The delete buttons the grid offers for the card, each a form that DELETEs it
     *
     * @return list<string>
     */
    private function deleteForms(Response $response, GiftCardInterface $giftCard): array
    {
        self::assertSame(200, $response->getStatusCode());

        return self::textsOf($response, sprintf(
            '//form[@action="/admin/gift-cards/%d"][input[@name="_method"][@value="DELETE"]]',
            (int) $giftCard->getId(),
        ));
    }

    /**
     * A card created for a unit of an order the way add to cart creates one. It stays disabled until the order is
     * paid, which is what makes it pending
     */
    private function persistBoughtGiftCard(string $code, bool $enabled): GiftCardInterface
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $item = $this->addItem($order, 'GIFT_CARD_' . $code, 5000, true);
        $this->manager->persist($order);

        $giftCard = $this->persistGiftCard($code, 5000, $enabled);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnitInterface::class, $unit);
        $giftCard->setOrderItemUnit($unit);
        $this->manager->flush();

        return $giftCard;
    }

    private function persistCustomer(string $email): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $this->manager->persist($customer);

        return $customer;
    }

    private function persistDesign(string $code, string $name, int $position): GiftCardDesignInterface
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

        return $design;
    }
}
