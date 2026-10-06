<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * The show page offers sending as a form carrying a CSRF token, and the action only acts on a token issued for
 * exactly that purpose. The unit test covers the action on its own; this proves the token the page renders is one
 * the action accepts, and that the email really goes out.
 *
 * Only a card the customer can use is sent, the same rule the email on creation follows: the grid and the show page
 * leave the action out for any other card, and the action refuses one anyway
 */
final class SendGiftCardEmailActionTest extends AdminFunctionalTestCase
{
    /** What the admin is told when a card is not sent, per way of being unusable */
    private const NOT_SENT = [
        'disabled' => 'The gift card is disabled, so it was not sent.',
        'expired' => 'The gift card has expired, so it was not sent.',
        'without a balance' => 'The gift card has no balance left, so it was not sent.',
    ];

    /** @var list<string> the recipients of the emails delivered during the test */
    private array $recipients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipients = [];

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(MessageEvent::class, function (MessageEvent $event): void {
            $message = $event->getMessage();
            if ($event->isQueued() || !$message instanceof Email) {
                return;
            }

            foreach ($message->getTo() as $address) {
                $this->recipients[] = $address->getAddress();
            }
        });

        $this->logInAsAdministrator();
    }

    /** @test */
    public function it_emails_the_gift_card_from_its_show_page(): void
    {
        $giftCard = $this->persistGiftCardFor('customer@example.com');

        $response = $this->send($giftCard, $this->tokenOnShowPage($giftCard));

        self::assertTrue($response->isRedirect($this->showUri($giftCard)));
        self::assertSame(['customer@example.com'], $this->recipients);
        self::assertStringContainsString('The gift card was emailed to the customer', (string) $this->followRedirect($response)->getContent());
    }

    /** @test */
    public function it_tells_the_admin_when_there_is_nobody_to_email(): void
    {
        $giftCard = $this->persistGiftCard('NOCUSTOMER', 5000);

        $response = $this->send($giftCard, $this->tokenOnShowPage($giftCard));

        self::assertTrue($response->isRedirect($this->showUri($giftCard)));
        self::assertSame([], $this->recipients);
        self::assertStringContainsString(
            'The gift card has no customer email address, so it could not be sent.',
            (string) $this->followRedirect($response)->getContent(),
        );
    }

    /**
     * A card the customer cannot use would arrive as a gift that does not work. The page the admin sends it from was
     * rendered while the card was still usable, which is how the action is reached for such a card at all: an order
     * cancelled or refunded in the meantime disables the cards it bought
     *
     * @test
     *
     * @dataProvider unusableStates
     */
    public function it_does_not_email_a_gift_card_the_customer_cannot_use(string $state): void
    {
        $giftCard = $this->persistGiftCardFor('customer@example.com');
        $token = $this->tokenOnShowPage($giftCard);

        $giftCard = $this->reloadGiftCard($giftCard);
        self::makeUnusable($giftCard, $state);
        $this->manager->flush();

        $response = $this->send($giftCard, $token);

        self::assertTrue($response->isRedirect($this->showUri($giftCard)));
        self::assertSame([], $this->recipients);
        self::assertStringContainsString(self::NOT_SENT[$state], (string) $this->followRedirect($response)->getContent());
    }

    /**
     * @test
     *
     * @dataProvider unusableStates
     */
    public function it_offers_sending_only_for_a_gift_card_the_customer_can_use(string $state): void
    {
        $usable = $this->persistGiftCardFor('customer@example.com', 'USABLECARD01');
        $unusable = $this->persistGiftCardFor('other@example.com', 'UNUSABLECARD01');
        self::makeUnusable($unusable, $state);
        $this->manager->flush();

        self::assertSame(1, $this->countSendForms($this->request('GET', $this->showUri($usable)), $usable));
        self::assertSame(0, $this->countSendForms($this->request('GET', $this->showUri($unusable)), $unusable));

        $grid = $this->request('GET', '/admin/gift-cards/');
        self::assertSame(200, $grid->getStatusCode());
        self::assertSame(1, $this->countSendForms($grid, $usable), 'the row of the usable card offers sending');
        self::assertSame(0, $this->countSendForms($grid, $unusable), 'the row of the unusable card does not');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableStates(): iterable
    {
        foreach (array_keys(self::NOT_SENT) as $state) {
            yield $state => [$state];
        }
    }

    /**
     * A card waiting in a cart is left out of the grid, but its show page can still be opened by its URL. Nobody has
     * paid for it, so it is not sent from there either
     *
     * @test
     */
    public function it_neither_offers_nor_sends_a_gift_card_waiting_in_a_cart(): void
    {
        $usable = $this->persistGiftCardFor('customer@example.com', 'USABLECARD01');
        $pending = $this->persistPendingGiftCardFor('buyer@example.com');
        self::assertTrue($pending->isPending());

        self::assertSame(0, $this->countSendForms($this->request('GET', $this->showUri($pending)), $pending));

        // Every card's form carries a token with the same id, so the one rendered for another card is accepted
        $response = $this->send($pending, $this->tokenOnShowPage($usable));

        self::assertTrue($response->isRedirect($this->showUri($pending)));
        self::assertSame([], $this->recipients);
        self::assertStringContainsString(self::NOT_SENT['disabled'], (string) $this->followRedirect($response)->getContent());
    }

    /**
     * A valid token issued for another action, here the one on the "create gift card product" button, does not
     * authorise sending
     *
     * @test
     */
    public function it_does_not_accept_a_token_issued_for_another_action(): void
    {
        $giftCard = $this->persistGiftCardFor('customer@example.com');

        $index = $this->request('GET', '/admin/gift-cards/');
        $token = self::valueOf($index, '//form[@action="/admin/gift-cards/create-product"]//input[@name="_csrf_token"]');

        self::assertSame(403, $this->send($giftCard, $token)->getStatusCode());
        self::assertSame([], $this->recipients);
    }

    /** @test */
    public function it_answers_not_found_for_an_unknown_gift_card(): void
    {
        $token = $this->tokenOnShowPage($this->persistGiftCardFor('customer@example.com'));

        self::assertSame(404, $this->request('POST', '/admin/gift-cards/987654/send-email', ['_csrf_token' => $token])->getStatusCode());
        self::assertSame([], $this->recipients);
    }

    private function persistGiftCardFor(string $email, string $code = 'SENDME', bool $enabled = true): GiftCardInterface
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical($email);
        $this->manager->persist($customer);

        $giftCard = $this->persistGiftCard($code, 5000, $enabled);
        $giftCard->setCustomer($customer);
        $this->manager->flush();

        return $giftCard;
    }

    /**
     * A card created for a unit in a cart, the way add to cart creates one: disabled until the order is paid
     */
    private function persistPendingGiftCardFor(string $email): GiftCardInterface
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $item = $this->addItem($order, 'GIFT_CARD', 5000, true);
        $this->manager->persist($order);

        $giftCard = $this->persistGiftCardFor($email, 'PENDINGCARD01', false);

        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $unit->setGiftCard($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    private static function makeUnusable(GiftCardInterface $giftCard, string $state): void
    {
        match ($state) {
            'disabled' => $giftCard->disable(),
            'expired' => $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day')),
            'without a balance' => $giftCard->setAmount(0),
            default => self::fail(sprintf('No way to make a gift card unusable is called "%s"', $state)),
        };
    }

    /**
     * How many forms on the page send the given card
     */
    private function countSendForms(Response $page, GiftCardInterface $giftCard): int
    {
        return count(self::textsOf($page, sprintf('//form[@action="%s/send-email"]', $this->showUri($giftCard))));
    }

    private function tokenOnShowPage(GiftCardInterface $giftCard): string
    {
        $show = $this->request('GET', $this->showUri($giftCard));
        self::assertSame(200, $show->getStatusCode());

        return self::valueOf($show, sprintf('//form[@action="%s/send-email"]//input[@name="_csrf_token"]', $this->showUri($giftCard)));
    }

    private function send(GiftCardInterface $giftCard, string $token): Response
    {
        return $this->request('POST', $this->showUri($giftCard) . '/send-email', ['_csrf_token' => $token]);
    }

    private function showUri(GiftCardInterface $giftCard): string
    {
        return sprintf('/admin/gift-cards/%d', (int) $giftCard->getId());
    }
}
