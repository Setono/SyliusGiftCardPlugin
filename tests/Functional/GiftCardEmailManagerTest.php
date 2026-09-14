<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class GiftCardEmailManagerTest extends GiftCardFunctionalTestCase
{
    private GiftCardEmailManagerInterface $emailManager;

    /** @var list<array{to: string, subject: string, html: string, attachments: list<array{type: string, filename: ?string, body: string}>}> */
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
                    'subject' => (string) $message->getSubject(),
                    'html' => (string) $message->getHtmlBody(),
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
        // the code is grouped for reading, by the normalizer rather than by 4-4-4-4 slicing that assumes 16 characters
        self::assertStringContainsString('EMAI-LTES-T000-0000-1', $email['html']);

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

    /**
     * The email is rendered outside of a request - from the order confirmation, or when an admin resends the
     * card - so the translator's current locale is the admin's or the CLI default. Subject and body have to be
     * written in the locale the card belongs to instead
     *
     * @test
     */
    public function it_translates_the_email_in_the_gift_cards_locale_not_the_current_one(): void
    {
        /** @var LocaleAwareInterface $translator */
        $translator = self::getContainer()->get('translator');
        $translator->setLocale('en_US');

        $giftCard = $this->createGiftCard('customer@example.com', $this->getDanishChannel());

        $this->emailManager->sendGiftCard($giftCard);

        self::assertCount(1, $this->sentEmails);

        $email = $this->sentEmails[0];
        // from src/Resources/translations/messages.da.yml
        self::assertSame('Nyt gavekort', $email['subject']);
        self::assertStringContainsString('Der er blevet oprettet et nyt gavekort til dig', $email['html']);
        self::assertStringContainsString('Saldo', $email['html']);
        self::assertStringNotContainsString('A new gift card was created for you', $email['html']);
    }

    private function createGiftCard(?string $customerEmail, ?ChannelInterface $channel = null): GiftCardInterface
    {
        $container = self::getContainer();

        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = $container->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('EMAILTEST00000001');
        $giftCard->setChannel($channel ?? $this->getChannel());
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

    /**
     * A Sylius customer has no locale of its own, so a gift card without an order takes the language of the
     * channel it was bought on
     */
    private function getDanishChannel(): ChannelInterface
    {
        $container = self::getContainer();

        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = $container->get('sylius.repository.channel');

        $channel = $channelRepository->findOneBy(['code' => 'TEST_CHANNEL_DA']);
        if ($channel instanceof ChannelInterface) {
            return $channel;
        }

        $currency = new Currency();
        $currency->setCode('DKK');
        $this->manager->persist($currency);

        $locale = new Locale();
        $locale->setCode('da_DK');
        $this->manager->persist($locale);

        /** @var ChannelFactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelFactory->createNamed('Danish channel');
        $channel->setCode('TEST_CHANNEL_DA');
        $channel->setBaseCurrency($currency);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->addLocale($locale);

        $this->manager->persist($channel);
        $this->manager->flush();

        return $channel;
    }
}
