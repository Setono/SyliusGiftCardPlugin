<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the admin's gift card show page tells about a card
 */
final class GiftCardShowPageTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->logInAsAdministrator();
    }

    /**
     * The card shows the message with the line breaks it was written with, so the show page does too. It is still the
     * customer's text, so it stays escaped. A message stored before symfony/form 6.4.31 may hold CR LF, which is one
     * line break as well
     *
     * @test
     */
    public function it_shows_the_message_with_its_line_breaks(): void
    {
        $giftCard = $this->persistGiftCard('SHOWPAGEMESSAGE1', 5000);
        $giftCard->setCustomMessage("Happy birthday,\nlove from <b>Anna</b>\r\nand Bob");
        $this->manager->flush();

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        self::assertStringContainsString(
            "<td>Happy birthday,<br />\nlove from &lt;b&gt;Anna&lt;/b&gt;<br />\r\nand Bob</td>",
            (string) $response->getContent(),
        );
    }

    /** @test */
    public function it_shows_a_dash_for_a_card_without_a_message(): void
    {
        $giftCard = $this->persistGiftCard('SHOWPAGEMESSAGE2', 5000);
        $giftCard->setCustomMessage(null);
        $this->manager->flush();

        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        self::assertSame(['-'], self::textsOf($response, '//tr[td[1]/strong[normalize-space() = "Custom message"]]/td[2]'));
    }

    /**
     * The same status the grid shows, as the same label
     *
     * @test
     */
    public function it_shows_the_status_of_the_card(): void
    {
        $usable = $this->persistGiftCard('SHOWPAGESTATUS01', 5000);
        $expired = $this->persistGiftCard('SHOWPAGESTATUS02', 5000);
        $expired->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->manager->flush();

        self::assertSame(['Usable'], $this->detail($this->show($usable), 'Status'));
        self::assertSame(['Expired'], $this->detail($this->show($expired), 'Status'));
        self::assertSame(
            ['expired'],
            self::textsOf($this->show($expired), '//tr[td[1]/strong[normalize-space() = "Status"]]//*[contains(@class, "ui orange label")]/@data-test-gift-card-status'),
        );
    }

    /** @test */
    public function it_links_the_order_the_card_was_bought_with(): void
    {
        $order = $this->persistOrder('000042');
        $giftCard = $this->persistGiftCardBoughtWith($order, 'SHOWPAGEBOUGHT01');

        $response = $this->show($giftCard);

        self::assertSame(['#000042'], $this->detail($response, 'Bought with order'));
        self::assertSame(
            [sprintf('/admin/orders/%d', (int) $order->getId())],
            self::textsOf($response, '//tr[td[1]/strong[normalize-space() = "Bought with order"]]/td[2]/a/@href'),
        );
    }

    /**
     * A card is created when the gift card is put in the cart, but the admin has no page for a cart to link to
     *
     * @test
     */
    public function it_says_when_the_card_is_still_in_a_cart(): void
    {
        $giftCard = $this->persistGiftCardBoughtWith($this->persistOrder(null), 'SHOWPAGEINCART01', enabled: false);

        $response = $this->show($giftCard);

        self::assertSame(['Pending'], $this->detail($response, 'Status'));
        self::assertSame(['In a cart, not ordered yet'], $this->detail($response, 'Bought with order'));
        self::assertSame([], self::textsOf($response, '//tr[td[1]/strong[normalize-space() = "Bought with order"]]/td[2]/a'));
    }

    /** @test */
    public function it_shows_a_dash_for_a_card_issued_in_the_admin(): void
    {
        $response = $this->show($this->persistGiftCard('SHOWPAGEADMIN01', 5000));

        self::assertSame(['-'], $this->detail($response, 'Bought with order'));
        self::assertSame(['-'], $this->detail($response, 'Applied to orders'));
    }

    /**
     * The orders the card was applied to, oldest first. A cart it was applied to is no order yet, and the admin has no
     * page to link one to
     *
     * @test
     */
    public function it_links_the_placed_orders_the_card_was_applied_to(): void
    {
        $giftCard = $this->persistGiftCard('SHOWPAGEAPPLIED1', 5000);
        $first = $this->persistOrder('000101');
        $second = $this->persistOrder('000102');
        $cart = $this->persistOrder(null);
        foreach ([$first, $second, $cart] as $order) {
            $order->addGiftCard($giftCard);
        }
        $this->manager->flush();

        $response = $this->show($giftCard);

        self::assertSame(['#000101, #000102'], $this->detail($response, 'Applied to orders'));
        self::assertSame(
            [sprintf('/admin/orders/%d', (int) $first->getId()), sprintf('/admin/orders/%d', (int) $second->getId())],
            self::textsOf($response, '//tr[td[1]/strong[normalize-space() = "Applied to orders"]]/td[2]/a/@href'),
        );
    }

    /**
     * A design has no page of its own, so its name leads to its edit page, next to its front image
     *
     * @test
     */
    public function it_links_the_design_with_its_front_image(): void
    {
        $design = $this->persistDesign('showpage', 'Birthday');
        $image = new GiftCardDesignImage();
        $image->setType(GiftCardDesignImageInterface::TYPE_FRONT);
        $image->setPath('ab/cd/front.png');
        $design->addImage($image);
        $giftCard = $this->persistGiftCard('SHOWPAGEDESIGN01', 5000);
        $giftCard->setDesign($design);
        $this->manager->flush();

        $response = $this->show($giftCard);

        $link = '//tr[td[1]/strong[normalize-space() = "Design"]]/td[2]/a';
        self::assertSame(['Birthday'], self::textsOf($response, $link));
        self::assertSame([sprintf('/admin/gift-card-designs/%d/edit', (int) $design->getId())], self::textsOf($response, $link . '/@href'));
        self::assertCount(1, self::textsOf($response, $link . '/img[contains(@src, "setono_sylius_gift_card_design_thumbnail")][contains(@src, "ab/cd/front.png")]'));
    }

    /**
     * The grid mails the customer from the email and opens the customer from the icon next to it, and the show page
     * does the same rather than the reverse
     *
     * @test
     */
    public function it_links_the_customer_the_way_the_grid_does(): void
    {
        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        $this->manager->persist($customer);
        $giftCard = $this->persistGiftCard('SHOWPAGECUSTOMER', 5000);
        $giftCard->setCustomer($customer);
        $this->manager->flush();

        $response = $this->show($giftCard);
        $index = $this->request('GET', '/admin/gift-cards/');

        $links = '//tr[td[1]/strong[normalize-space() = "Customer"]]/td[2]/a/@href';
        self::assertSame(['mailto:customer@example.com', sprintf('/admin/customers/%d', (int) $customer->getId())], self::textsOf($response, $links));
        self::assertSame(self::textsOf($response, $links), self::textsOf($index, '//tbody[@data-test-grid-table-body]/tr/td[2]/a/@href'));
    }

    private function show(GiftCardInterface $giftCard): Response
    {
        $response = $this->request('GET', sprintf('/admin/gift-cards/%d', (int) $giftCard->getId()));
        self::assertSame(200, $response->getStatusCode());

        return $response;
    }

    /**
     * @return list<string> the value of the row of the details table with the given label
     */
    private function detail(Response $response, string $label): array
    {
        return self::textsOf($response, sprintf('//tr[td[1]/strong[normalize-space() = "%s"]]/td[2]', $label));
    }

    /**
     * An order of the test channel, placed under the given number, or a cart without one
     */
    private function persistOrder(?string $number): Order
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        if (null !== $number) {
            $order->setNumber($number);
            $order->setState(OrderInterface::STATE_NEW);
            $order->setCheckoutCompletedAt(new \DateTime());
        }

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function persistGiftCardBoughtWith(Order $order, string $code, bool $enabled = true): GiftCardInterface
    {
        $item = $this->addItem($order, 'GIFT_CARD_' . $code, 5000, true);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnitInterface::class, $unit);

        $giftCard = $this->persistGiftCard($code, 5000, $enabled);
        $giftCard->setOrderItemUnit($unit);
        $this->manager->flush();

        return $giftCard;
    }

    private function persistDesign(string $code, string $name): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setCurrentLocale('en_US');
        $design->setFallbackLocale('en_US');
        $design->setName($name);
        $design->setPosition(1);
        $design->addChannel($this->getChannel());

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }
}
