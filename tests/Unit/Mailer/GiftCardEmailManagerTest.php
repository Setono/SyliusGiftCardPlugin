<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Mailer;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManager;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Mailer\Sender\SenderInterface;

/**
 * Which cards go into an email, and which of them as a PDF, is decided here; what the email says about them is
 * rendered by the templates, which GiftCardEmailManagerTest in the functional suite covers
 */
final class GiftCardEmailManagerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<SenderInterface> */
    private ObjectProphecy $sender;

    /**
     * What the sender was handed, with the contents of every attachment as it was when the email went out
     *
     * @var list<array{code: string, recipients: array<array-key, mixed>, data: array<array-key, mixed>, attachments: array<string, string>}>
     */
    private array $sent = [];

    private ChannelInterface $channel;

    protected function setUp(): void
    {
        $this->sent = [];
        $sent = &$this->sent;

        // a static closure, as Prophecy binds any other to the prophecy, where the assertions do not exist
        $this->sender = $this->prophesize(SenderInterface::class);
        $this->sender->send(Argument::cetera())->will(static function (array $arguments) use (&$sent): void {
            [$code, $recipients, $data, $attachments] = $arguments;
            self::assertIsString($code);
            self::assertIsArray($recipients);
            self::assertIsArray($data);
            self::assertIsArray($attachments);

            $contents = [];
            foreach ($attachments as $path) {
                self::assertIsString($path);
                self::assertFileExists($path, 'the attachment has to exist while the email is sent');
                $contents[$path] = (string) file_get_contents($path);
            }

            $sent[] = ['code' => $code, 'recipients' => $recipients, 'data' => $data, 'attachments' => $contents];
        });

        $locale = new Locale();
        $locale->setCode('en_US');

        $this->channel = new Channel();
        $this->channel->setDefaultLocale($locale);
    }

    /**
     * The order email is sent when the order is paid. A virtual card is delivered by it, while a physical one is on
     * its way with its code printed on it, so only the virtual card's PDF is attached
     *
     * @test
     */
    public function it_emails_the_buyer_every_card_on_the_order_attaching_only_the_virtual_ones(): void
    {
        $virtual = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $physical = $this->giftCard('PHYSICAL001', GiftCardDeliveryType::Physical);
        $order = $this->order('buyer@example.com', [$virtual, $physical]);

        $this->emailManager()->sendGiftCardsFromOrder($order, [$virtual, $physical]);

        self::assertCount(1, $this->sent);
        $email = $this->sent[0];

        self::assertSame(Emails::GIFT_CARDS_FROM_ORDER, $email['code']);
        self::assertSame(['buyer@example.com'], $email['recipients']);
        self::assertSame([
            'order' => $order,
            'channel' => $this->channel,
            'localeCode' => 'da_DK',
            'giftCards' => [$virtual, $physical],
            'disclosePhysicalCards' => false,
        ], $email['data']);
        self::assertSame(['gift-card-VIRTUAL0001.pdf' => '%PDF VIRTUAL0001'], $this->attachmentsByFilename($email['attachments']));
    }

    /**
     * setono_sylius_gift_card.delivery.email_physical_cards makes the order email a digital backup of the physical
     * cards too
     *
     * @test
     */
    public function it_attaches_the_physical_cards_too_when_configured_to(): void
    {
        $virtual = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $physical = $this->giftCard('PHYSICAL001', GiftCardDeliveryType::Physical);
        $order = $this->order('buyer@example.com', [$virtual, $physical]);

        $this->emailManager(emailPhysicalCards: true)->sendGiftCardsFromOrder($order, [$virtual, $physical]);

        self::assertCount(1, $this->sent);
        self::assertTrue($this->sent[0]['data']['disclosePhysicalCards']);
        self::assertSame([
            'gift-card-VIRTUAL0001.pdf' => '%PDF VIRTUAL0001',
            'gift-card-PHYSICAL001.pdf' => '%PDF PHYSICAL001',
        ], $this->attachmentsByFilename($this->sent[0]['attachments']));
    }

    /** @test */
    public function it_sends_no_order_email_without_gift_cards(): void
    {
        $this->emailManager()->sendGiftCardsFromOrder($this->order('buyer@example.com', []), []);

        self::assertSame([], $this->sent);
    }

    /** @test */
    public function it_sends_no_order_email_without_an_address_to_send_it_to(): void
    {
        $giftCard = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);

        $withoutCustomer = $this->order(null, [$giftCard]);
        $this->emailManager()->sendGiftCardsFromOrder($withoutCustomer, [$giftCard]);

        $withoutEmail = $this->order(null, [$giftCard]);
        $withoutEmail->setCustomer(new Customer());
        $this->emailManager()->sendGiftCardsFromOrder($withoutEmail, [$giftCard]);

        self::assertSame([], $this->sent);
    }

    /**
     * Sending a single card is what an admin does to issue a card or to replace one the customer lost, so the code
     * and the PDF go out whatever the delivery type, and whatever the configuration says about the order email
     *
     * @test
     */
    public function it_emails_a_single_card_to_its_customer_with_its_pdf_whatever_its_delivery_type(): void
    {
        $giftCard = $this->giftCard('PHYSICAL001', GiftCardDeliveryType::Physical);
        $giftCard->setCustomer($this->customer('holder@example.com'));

        $this->emailManager()->sendGiftCard($giftCard);

        self::assertCount(1, $this->sent);
        $email = $this->sent[0];

        self::assertSame(Emails::GIFT_CARD, $email['code']);
        self::assertSame(['holder@example.com'], $email['recipients']);
        self::assertSame([
            'channel' => $this->channel,
            'localeCode' => 'en_US',
            'giftCards' => [$giftCard],
            'disclosePhysicalCards' => true,
        ], $email['data']);
        self::assertSame(['gift-card-PHYSICAL001.pdf' => '%PDF PHYSICAL001'], $this->attachmentsByFilename($email['attachments']));
    }

    /**
     * A customer carries no locale, so a card bought in the shop is written in the locale it was bought in, and one
     * issued in the admin in its channel's default locale, the way its PDF is
     *
     * @test
     */
    public function it_writes_a_single_card_in_the_locale_it_was_bought_in(): void
    {
        $giftCard = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $giftCard->setCustomer($this->customer('holder@example.com'));
        $this->order('buyer@example.com', [$giftCard]);

        $this->emailManager()->sendGiftCard($giftCard);

        self::assertCount(1, $this->sent);
        self::assertSame('da_DK', $this->sent[0]['data']['localeCode']);
    }

    /** @test */
    public function it_sends_no_single_card_without_an_address_to_send_it_to(): void
    {
        $withoutCustomer = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $this->emailManager()->sendGiftCard($withoutCustomer);

        $withoutEmail = $this->giftCard('VIRTUAL0002', GiftCardDeliveryType::Virtual);
        $withoutEmail->setCustomer(new Customer());
        $this->emailManager()->sendGiftCard($withoutEmail);

        self::assertSame([], $this->sent);
    }

    /** @test */
    public function it_removes_the_attachments_once_the_email_is_sent(): void
    {
        $giftCard = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $giftCard->setCustomer($this->customer('holder@example.com'));

        $this->emailManager()->sendGiftCard($giftCard);

        self::assertCount(1, $this->sent);
        $this->assertRemoved(array_keys($this->sent[0]['attachments']));
    }

    /**
     * A PDF holds a spendable code, so it may not be left behind in the temporary directory when the mailer fails
     *
     * @test
     */
    public function it_removes_the_attachments_when_sending_fails(): void
    {
        /** @var list<string> $attachments */
        $attachments = [];

        $this->sender->send(Argument::cetera())->will(static function (array $arguments) use (&$attachments): void {
            self::assertIsArray($arguments[3]);
            foreach ($arguments[3] as $path) {
                self::assertIsString($path);
                $attachments[] = $path;
            }

            throw new \RuntimeException('The mailer is down');
        });

        $giftCard = $this->giftCard('VIRTUAL0001', GiftCardDeliveryType::Virtual);
        $giftCard->setCustomer($this->customer('holder@example.com'));

        try {
            $this->emailManager()->sendGiftCard($giftCard);
            self::fail('The failure to send should have been passed on');
        } catch (\RuntimeException $e) {
            self::assertSame('The mailer is down', $e->getMessage());
        }

        self::assertCount(1, $attachments);
        $this->assertRemoved($attachments);
    }

    private function emailManager(bool $emailPhysicalCards = false): GiftCardEmailManager
    {
        $pdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $pdfGenerator->generate(Argument::type(GiftCardInterface::class))->will(
            static fn (array $arguments): string => sprintf('%%PDF %s', (string) ($arguments[0] instanceof GiftCardInterface ? $arguments[0]->getCode() : '')),
        );

        return new GiftCardEmailManager($this->sender->reveal(), $pdfGenerator->reveal(), $emailPhysicalCards);
    }

    private function giftCard(string $code, GiftCardDeliveryType $deliveryType): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);
        $giftCard->setChannel($this->channel);
        $giftCard->setDeliveryType($deliveryType);

        return $giftCard;
    }

    /**
     * An order placed in Danish on the channel, whose units carry the given cards
     *
     * @param list<GiftCard> $giftCards
     */
    private function order(?string $email, array $giftCards): Order
    {
        $order = new Order();
        $order->setChannel($this->channel);
        $order->setLocaleCode('da_DK');

        if (null !== $email) {
            $order->setCustomer($this->customer($email));
        }

        $item = new OrderItem();
        $order->addItem($item);
        foreach ($giftCards as $giftCard) {
            $giftCard->setOrderItemUnit(new OrderItemUnit($item));
        }

        return $order;
    }

    private function customer(string $email): Customer
    {
        $customer = new Customer();
        $customer->setEmail($email);

        return $customer;
    }

    /**
     * @param array<string, string> $attachments contents by path
     *
     * @return array<string, string> contents by file name
     */
    private function attachmentsByFilename(array $attachments): array
    {
        $byFilename = [];
        foreach ($attachments as $path => $contents) {
            $byFilename[basename($path)] = $contents;
        }

        return $byFilename;
    }

    /**
     * @param list<string> $paths
     */
    private function assertRemoved(array $paths): void
    {
        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
            self::assertDirectoryDoesNotExist(dirname($path), 'the directory made for the attachments should be gone too');
        }
    }
}
