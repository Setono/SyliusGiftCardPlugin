<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

final class GiftCardEmailManagerTest extends GiftCardFunctionalTestCase
{
    private GiftCardEmailManagerInterface $emailManager;

    /** @var list<array{to: string, attachments: list<array{type: string, filename: ?string, body: string}>}> */
    private array $sentEmails = [];

    protected function setUp(): void
    {
        parent::setUp();
        $container = self::getContainer();

        /** @var GiftCardEmailManagerInterface $emailManager */
        $emailManager = $container->get(GiftCardEmailManagerInterface::class);
        $this->emailManager = $emailManager;

        $this->sentEmails = [];

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->addListener(
            MessageEvent::class,
            function (MessageEvent $event): void {
                // The mailer may dispatch the event twice (once queued via messenger, once on delivery); only keep the
                // delivered message so the assertions count actual emails
                if ($event->isQueued()) {
                    return;
                }

                $message = $event->getMessage();
                if (!$message instanceof Email) {
                    return;
                }

                // Capture the attachment bytes here, while the temporary PDF file still exists — the email manager
                // unlinks it once send() returns, so reading it after the fact would fail
                $attachments = [];
                foreach ($message->getAttachments() as $attachment) {
                    $attachments[] = [
                        'type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                        'filename' => $attachment->getPreparedHeaders()->getHeaderParameter('content-disposition', 'filename'),
                        'body' => $attachment->getBody(),
                    ];
                }

                $this->sentEmails[] = [
                    'to' => $message->getTo()[0]->getAddress(),
                    'attachments' => $attachments,
                ];
            },
        );
    }

    /** @test */
    public function it_emails_a_gift_card_to_its_customer_with_a_pdf_attachment(): void
    {
        $giftCard = $this->createGiftCard('customer@example.com');

        $this->emailManager->sendGiftCard($giftCard);

        self::assertCount(1, $this->sentEmails);

        $email = $this->sentEmails[0];
        self::assertSame('customer@example.com', $email['to']);

        self::assertCount(1, $email['attachments'], 'the gift card PDF should be attached');

        $attachment = $email['attachments'][0];
        self::assertSame('application/pdf', $attachment['type']);
        self::assertSame('gift-card-EMAILTEST00000001.pdf', $attachment['filename']);
        self::assertStringStartsWith('%PDF', $attachment['body'], 'the attachment should be a valid PDF');
    }

    /** @test */
    public function it_does_not_email_a_gift_card_without_a_customer(): void
    {
        $giftCard = $this->createGiftCard(null);

        $this->emailManager->sendGiftCard($giftCard);

        self::assertCount(0, $this->sentEmails);
    }

    private function createGiftCard(?string $customerEmail): GiftCardInterface
    {
        $container = self::getContainer();

        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = $container->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('EMAILTEST00000001');
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->enable();

        if (null !== $customerEmail) {
            $customer = new Customer();
            $customer->setEmail($customerEmail);
            $this->manager->persist($customer);
            $giftCard->setCustomer($customer);
        }

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }
}
