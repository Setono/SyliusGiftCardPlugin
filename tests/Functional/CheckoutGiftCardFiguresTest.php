<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Bundle\UiBundle\Registry\TemplateBlockRegistryInterface;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMemberInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cart shows what the applied gift cards cover and what remains to pay below its order total, and every checkout
 * step up to placing the order has to show the same. The gift cards pay for the order rather than discount it, so the
 * order total the checkout's summaries show stays the full amount, and without the figures the customer would be shown
 * that amount as though no card had been applied.
 *
 * The pages are requested through the kernel with the cart the visitor's session names, in the checkout state each
 * step is reached in, so they are Sylius' own checkout templates with the blocks the plugin adds to their summaries:
 * the sidebar of the address, shipping and payment steps, and the order summary of the complete step. The cart is
 * processed the way the shop processes it, so it carries the shipment and the payment for the rest the checkout
 * gives it
 */
final class CheckoutGiftCardFiguresTest extends AdminFunctionalTestCase
{
    private const HOSTNAME = 'shop.example.test';

    private const ITEM_PRICE = 10000;

    private const SHIPPING_PRICE = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getChannel()->setHostname(self::HOSTNAME);

        // the shop refuses gift cards until it is set up
        $this->createGiftCardPaymentMethod();
        $this->createCashPaymentMethod();
        $this->createShippingMethod();
        $this->manager->flush();
    }

    /**
     * The payment step is skipped when the cards cover the whole order, so the other steps are where the customer
     * learns that the cards pay for it
     *
     * @test
     */
    public function every_step_shows_that_a_card_covering_the_whole_order_leaves_nothing_to_pay(): void
    {
        $cartId = $this->createCart($this->createEnabledGiftCard('CHECKOUTFIGURES1', 15000));

        foreach (['address' => OrderCheckoutStates::STATE_CART, 'select-shipping' => OrderCheckoutStates::STATE_ADDRESSED] as $step => $state) {
            $page = $this->step($cartId, $step, $state);

            self::assertSame(['$110.00'], self::textsOf($page, '//*[@id="sylius-summary-grand-total"]'), sprintf('the %s step should show the full order total', $step));
            self::assertSame(['-$110.00'], $this->figure($page, 'gift-cards-total'), sprintf('the %s step should show what the card covers', $step));
            self::assertSame(['$0.00'], $this->figure($page, 'gift-cards-remaining-total'), sprintf('the %s step should show that nothing remains to pay', $step));
        }

        $complete = $this->step($cartId, 'complete', OrderCheckoutStates::STATE_PAYMENT_SKIPPED);

        // Sylius' total is what the order costs, which the card leaves as it is, and there is no payment for the rest
        self::assertSame(['Total: $110.00'], self::textsOf($complete, '//*[@data-test-order-total]'));
        self::assertSame([], self::textsOf($complete, '//*[@data-test-payment-price]'));
        self::assertSame(['-$110.00'], $this->figure($complete, 'gift-cards-total'));
        self::assertSame(['$0.00'], $this->figure($complete, 'gift-cards-remaining-total'));
    }

    /** @test */
    public function every_step_shows_what_remains_to_pay_when_the_card_covers_part_of_the_order(): void
    {
        $cartId = $this->createCart($this->createEnabledGiftCard('CHECKOUTFIGURES2', 6000));

        foreach ([
            'address' => OrderCheckoutStates::STATE_CART,
            'select-shipping' => OrderCheckoutStates::STATE_ADDRESSED,
            'select-payment' => OrderCheckoutStates::STATE_SHIPPING_SELECTED,
        ] as $step => $state) {
            $page = $this->step($cartId, $step, $state);

            self::assertSame(['$110.00'], self::textsOf($page, '//*[@id="sylius-summary-grand-total"]'), sprintf('the %s step should show the full order total', $step));
            self::assertSame(['-$60.00'], $this->figure($page, 'gift-cards-total'), sprintf('the %s step should show what the card covers', $step));
            self::assertSame(['$50.00'], $this->figure($page, 'gift-cards-remaining-total'), sprintf('the %s step should show what remains to pay', $step));
        }

        $complete = $this->step($cartId, 'complete', OrderCheckoutStates::STATE_PAYMENT_SELECTED);

        // what remains to pay is what the payment for the rest, listed above the figures, charges
        self::assertSame(['Total: $110.00'], self::textsOf($complete, '//*[@data-test-order-total]'));
        self::assertSame(['$50.00'], self::textsOf($complete, '//*[@data-test-payment-price]'));
        self::assertSame(['-$60.00'], $this->figure($complete, 'gift-cards-total'));
        self::assertSame(['$50.00'], $this->figure($complete, 'gift-cards-remaining-total'));
    }

    /** @test */
    public function no_step_shows_gift_card_figures_without_a_gift_card(): void
    {
        $cartId = $this->createCart(null);

        foreach ([
            'address' => OrderCheckoutStates::STATE_CART,
            'select-shipping' => OrderCheckoutStates::STATE_ADDRESSED,
            'select-payment' => OrderCheckoutStates::STATE_SHIPPING_SELECTED,
            'complete' => OrderCheckoutStates::STATE_PAYMENT_SELECTED,
        ] as $step => $state) {
            self::assertSame([], self::textsOf($this->step($cartId, $step, $state), '//*[@data-test-gift-card-totals]'), sprintf('the %s step', $step));
        }
    }

    /**
     * Sylius fires no event inside its totals, so the figures are a block of their own on the event that renders each
     * summary, and their priority is what places them: right below the summary, ahead of whatever Sylius renders
     * after it (the support box in the sidebar, the shipping step's legacy before support event, the complete step's
     * legacy after summary event and the cart's checkout button)
     *
     * @test
     *
     * @dataProvider summaries
     *
     * @param non-empty-list<string> $events
     */
    public function the_figures_follow_the_summary_right_away(array $events, string $summary): void
    {
        /** @var TemplateBlockRegistryInterface $registry */
        $registry = self::getContainer()->get(TemplateBlockRegistryInterface::class);

        $blocks = array_map(static fn (TemplateBlock $block): string => $block->getName(), $registry->findEnabledForEvents($events));

        $position = array_search($summary, $blocks, true);
        self::assertIsInt($position, sprintf('Sylius renders no "%s" block on %s', $summary, implode(', ', $events)));
        self::assertSame('setono_gift_card_totals', $blocks[$position + 1] ?? null, sprintf('the blocks on %s are %s', implode(', ', $events), implode(', ', $blocks)));
    }

    /**
     * The events each page renders its summary through, as Sylius' templates fire them, with the name of Sylius'
     * block rendering that summary
     *
     * @return iterable<string, array{non-empty-list<string>, string}>
     */
    public static function summaries(): iterable
    {
        yield 'cart' => [['sylius.shop.cart.summary'], 'totals'];
        yield 'address step' => [['sylius.shop.checkout.address.sidebar', 'sylius.shop.checkout.sidebar'], 'summary'];
        yield 'shipping step' => [['sylius.shop.checkout.select_shipping.sidebar', 'sylius.shop.checkout.sidebar'], 'summary'];
        yield 'payment step' => [['sylius.shop.checkout.select_payment.sidebar', 'sylius.shop.checkout.sidebar'], 'summary'];
        yield 'complete step' => [['sylius.shop.checkout.complete.summary'], 'content'];
    }

    /**
     * Requests a checkout step with the cart in the checkout state the step is reached in
     */
    private function step(int $cartId, string $step, string $checkoutState): Response
    {
        $cart = $this->manager->find(Order::class, $cartId);
        self::assertInstanceOf(Order::class, $cart);
        $cart->setCheckoutState($checkoutState);
        $this->manager->flush();

        $response = $this->request('GET', sprintf('http://%s/en_US/checkout/%s', self::HOSTNAME, $step));
        self::assertSame(200, $response->getStatusCode(), sprintf('the %s step should render', $step));

        return $response;
    }

    /**
     * @return list<string> the amount of the gift card figure carrying the given test attribute, once per time the
     *                      page shows it
     */
    private function figure(Response $page, string $row): array
    {
        return self::textsOf($page, sprintf('//tr[@data-test-%s]/td[last()]', $row));
    }

    /**
     * A cart holding one ordinary item that ships, with the gift card applied when one is given, processed the way
     * the shop processes a cart: it costs ITEM_PRICE plus SHIPPING_PRICE, and its payment is sized to what the card
     * leaves to pay. The visitor's session names it
     */
    private function createCart(?GiftCardInterface $giftCard): int
    {
        $address = new Address();
        $address->setFirstName('Gift');
        $address->setLastName('Tester');
        $address->setStreet('1 Main Street');
        $address->setCity('Springfield');
        $address->setPostcode('12345');
        $address->setCountryCode('US');

        $cart = new Order();
        $cart->setChannel($this->getChannel());
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $cart->setShippingAddress($address);
        $cart->setBillingAddress(clone $address);
        $this->addItem($cart, 'CAP', self::ITEM_PRICE)->getVariant()?->setShippingRequired(true);

        /** @var OrderProcessorInterface $orderProcessor */
        $orderProcessor = self::getContainer()->get('sylius.order_processing.order_processor');
        $orderProcessor->process($cart);

        if (null !== $giftCard) {
            /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
            $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');
            $redemptionMethod->apply($cart, $giftCard);
        }

        $this->manager->persist($cart);
        $this->manager->flush();

        self::assertSame(self::ITEM_PRICE + self::SHIPPING_PRICE, $cart->getTotal(), 'precondition: the cart costs the item and its shipping');

        $cartId = (int) $cart->getId();
        $this->startSession([sprintf('_sylius.cart.%s', (string) $this->getChannel()->getCode()) => $cartId]);

        return $cartId;
    }

    /**
     * A shipping method charging SHIPPING_PRICE for shipping to the United States, where the cart ships to
     */
    private function createShippingMethod(): void
    {
        $container = self::getContainer();

        $country = new Country();
        $country->setCode('US');
        $this->manager->persist($country);

        /** @var FactoryInterface<ZoneMemberInterface> $zoneMemberFactory */
        $zoneMemberFactory = $container->get('sylius.factory.zone_member');
        $member = $zoneMemberFactory->createNew();
        $member->setCode('US');

        /** @var FactoryInterface<ZoneInterface> $zoneFactory */
        $zoneFactory = $container->get('sylius.factory.zone');
        $zone = $zoneFactory->createNew();
        $zone->setCode('US');
        $zone->setName('United States');
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $zone->addMember($member);
        $this->manager->persist($zone);

        /** @var FactoryInterface<ShippingMethodInterface> $shippingMethodFactory */
        $shippingMethodFactory = $container->get('sylius.factory.shipping_method');
        $shippingMethod = $shippingMethodFactory->createNew();
        $shippingMethod->setCode('post');
        $shippingMethod->setCurrentLocale('en_US');
        $shippingMethod->setFallbackLocale('en_US');
        $shippingMethod->setName('Post');
        $shippingMethod->setZone($zone);
        $shippingMethod->setCalculator('flat_rate');
        $shippingMethod->setConfiguration([(string) $this->getChannel()->getCode() => ['amount' => self::SHIPPING_PRICE]]);
        $shippingMethod->addChannel($this->getChannel());
        $this->manager->persist($shippingMethod);
    }
}
