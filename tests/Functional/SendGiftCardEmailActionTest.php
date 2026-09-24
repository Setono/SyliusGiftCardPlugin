<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * The show page offers sending as a form carrying a CSRF token, and the action only acts on a token issued for
 * exactly that purpose. The unit test covers the action on its own; this proves the token the page renders is one
 * the action accepts, and that the email really goes out
 */
final class SendGiftCardEmailActionTest extends AdminFunctionalTestCase
{
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

    private function persistGiftCardFor(string $email): GiftCardInterface
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical($email);
        $this->manager->persist($customer);

        $giftCard = $this->persistGiftCard('SENDME', 5000);
        $giftCard->setCustomer($customer);
        $this->manager->flush();

        return $giftCard;
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
