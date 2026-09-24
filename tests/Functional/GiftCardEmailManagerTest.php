<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManager;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Mailer\Sender\SenderInterface;
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

        // the email is the delivery, so it has to carry everything the card says and what to do with it
        self::assertStringContainsString('Happy birthday!', $email['html']);
        self::assertStringContainsString('Valid until', $email['html']);
        self::assertStringContainsString('Enter the code at checkout', $email['html']);
    }

    /**
     * A physical gift card is shipped with its code printed on it. Emailing the code when the order is paid would
     * make the card spendable before it arrives, so the email only says that the card will be shipped
     *
     * @test
     */
    public function it_does_not_email_a_physical_gift_cards_code_or_pdf_when_the_order_is_paid(): void
    {
        $giftCard = $this->createGiftCard('customer@example.com', deliveryType: GiftCardDeliveryType::Physical);

        $this->emailManager->sendGiftCardsFromOrder($this->createOrder($giftCard), [$giftCard]);

        self::assertCount(1, $this->sentEmails);

        $email = $this->sentEmails[0];
        self::assertStringContainsString('000000042', $email['html'], 'precondition: this is the order email');
        self::assertCount(0, $email['attachments'], 'a physical gift card should not be attached as a PDF');
        self::assertStringNotContainsString('EMAI-LTES-T000-0000-1', $email['html']);
        self::assertStringNotContainsString('EMAILTEST00000001', $email['html']);
        self::assertStringContainsString('will be shipped to you', $email['html']);
        self::assertStringContainsString('The code is printed on the card itself', $email['html']);
        self::assertStringNotContainsString('attached to this email as a PDF', $email['html']);
        self::assertStringNotContainsString('Enter the code at checkout', $email['html']);

        // the balance is still worth telling the buyer about
        self::assertStringContainsString('Balance', $email['html']);
    }

    /**
     * Merchants who want the digital backup anyway turn setono_sylius_gift_card.delivery.email_physical_cards on
     *
     * @test
     */
    public function it_emails_a_physical_gift_cards_code_and_pdf_when_the_order_is_paid_if_configured_to(): void
    {
        $container = self::getContainer();

        /** @var SenderInterface $sender */
        $sender = $container->get('sylius.email_sender');

        /** @var GiftCardPdfGeneratorInterface $pdfGenerator */
        $pdfGenerator = $container->get(GiftCardPdfGeneratorInterface::class);

        $emailManager = new GiftCardEmailManager($sender, $pdfGenerator, true);

        $giftCard = $this->createGiftCard('customer@example.com', deliveryType: GiftCardDeliveryType::Physical);

        $emailManager->sendGiftCardsFromOrder($this->createOrder($giftCard), [$giftCard]);

        self::assertCount(1, $this->sentEmails);

        $email = $this->sentEmails[0];
        self::assertCount(1, $email['attachments'], 'the gift card PDF should be attached');
        self::assertSame('gift-card-EMAILTEST00000001.pdf', $email['attachments'][0]['filename']);
        self::assertStringContainsString('EMAI-LTES-T000-0000-1', $email['html']);
        // the card is still shipped, so the customer is still told it is coming
        self::assertStringContainsString('will be shipped to you', $email['html']);
        self::assertStringNotContainsString('The code is printed on the card itself', $email['html']);
    }

    /**
     * Sending a gift card from the admin is how a physical card the customer lost, or that never arrived, is
     * replaced. The admin asked for the customer to have the code, so it is disclosed with the default
     * configuration, and the email does not claim that a card is being shipped
     *
     * @test
     */
    public function it_emails_a_physical_gift_cards_code_and_pdf_when_an_admin_sends_it(): void
    {
        $giftCard = $this->createGiftCard('customer@example.com', deliveryType: GiftCardDeliveryType::Physical);

        $this->emailManager->sendGiftCard($giftCard);

        self::assertCount(1, $this->sentEmails);

        $email = $this->sentEmails[0];
        self::assertCount(1, $email['attachments'], 'the gift card PDF should be attached');
        self::assertSame('gift-card-EMAILTEST00000001.pdf', $email['attachments'][0]['filename']);
        self::assertStringStartsWith('%PDF', $email['attachments'][0]['body']);
        self::assertStringContainsString('EMAI-LTES-T000-0000-1', $email['html']);
        self::assertStringContainsString('attached to this email as a PDF', $email['html']);
        self::assertStringContainsString('Enter the code at checkout', $email['html']);
        self::assertStringNotContainsString('shipped', $email['html']);
        self::assertStringNotContainsString('The code is printed on the card itself', $email['html']);
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

    private function createGiftCard(
        ?string $customerEmail,
        ?ChannelInterface $channel = null,
        GiftCardDeliveryType $deliveryType = GiftCardDeliveryType::Virtual,
    ): GiftCardInterface {
        $container = self::getContainer();

        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = $container->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('EMAILTEST00000001');
        $giftCard->setChannel($channel ?? $this->getChannel());
        $giftCard->setDeliveryType($deliveryType);
        $giftCard->setCustomMessage('Happy birthday!');
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
     * The order a gift card was bought on, as far as the order email reads it
     */
    private function createOrder(GiftCardInterface $giftCard): OrderInterface
    {
        $order = new Order();
        $order->setNumber('000000042');
        $order->setChannel($giftCard->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setCustomer($giftCard->getCustomer());

        return $order;
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
