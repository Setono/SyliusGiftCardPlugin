<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Cart\CartGiftCardHandlerInterface;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardCurrencyMismatchException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\Factory\GiftCardInformationFactoryInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsEligible;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Currency\CurrencyStorageInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\ExchangeRate;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A shopper on a USD channel who has switched the shop to EUR. Sylius keeps the order in the channel's base currency
 * all the same: the cart contexts stamp a new cart with the base currency, and the other currencies a channel offers
 * only change how amounts are rendered (the shop's money macro converts from the base currency when printing), at the
 * exchange rate the shop keeps. A gift card is held in the currency it is bought in: a card bought for 40.00 EUR while
 * browsing in euros holds 40.00 EUR, costs that converted into dollars, and pays converted into the currency of the
 * order it is redeemed on (#488). Here a dollar is worth 0.80 EUR
 */
final class DisplayCurrencyGiftCardTest extends GiftCardFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->getChannel();
        $channel->setHostname('shop.example.test');

        $euro = new Currency();
        $euro->setCode('EUR');
        $this->manager->persist($euro);
        $channel->addCurrency($euro);

        $dollar = $channel->getBaseCurrency();
        self::assertNotNull($dollar);

        $exchangeRate = new ExchangeRate();
        $exchangeRate->setSourceCurrency($dollar);
        $exchangeRate->setTargetCurrency($euro);
        $exchangeRate->setRatio(0.8);
        $this->manager->persist($exchangeRate);

        $this->manager->flush();

        // the gift card payments are made with it, and the shop refuses gift cards until it is set up
        $this->createGiftCardPaymentMethod();

        $this->browseInEuro();
    }

    /**
     * The amount is typed in euros and the card holds them, while its line costs them in dollars, the way every price
     * this shopper sees in euros is a dollar price converted. A unit added by raising the quantity gets a card held the
     * same way
     *
     * @test
     */
    public function it_holds_a_card_bought_while_browsing_in_another_currency_in_that_currency(): void
    {
        $cart = $this->cart();
        $item = $this->addItem($cart, 'GIFT_CARD', 5000, giftCard: true);

        /** @var GiftCardInformationFactoryInterface $informationFactory */
        $informationFactory = self::getContainer()->get(GiftCardInformationFactoryInterface::class);
        $information = $informationFactory->createNew($cart, $item);
        self::assertSame('EUR', $information->getCurrencyCode(), 'the amount should be typed in the currency the shopper browses in');
        self::assertSame(4000, $information->getAmount(), 'the amount should start at the $50.00 price shown in euros');

        $this->handler()->handle(new AddToCartCommand($cart, $item, $information));
        $this->processor()->process($cart);

        self::assertSame(5000, $item->getUnitPrice(), 'the line should cost the 40.00 EUR in dollars');

        // bumping the quantity in the cart adds a unit without a card, which reconciliation issues one for
        new OrderItemUnit($item);
        $this->manager->persist($cart);
        $this->operator()->reconcile($cart);

        self::assertCount(2, $item->getUnits());
        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            self::assertSame('EUR', $giftCard->getCurrencyCode(), 'the card should be held in the currency it was bought in');
            self::assertSame(4000, $giftCard->getAmount(), 'the card should hold the amount the shopper chose');
            self::assertSame(4000, $giftCard->getInitialAmount());
        }
    }

    /**
     * 40.00 EUR is $50.00, so the card pays the whole $50.00 order, and the payment is in dollars, the order's currency
     *
     * @test
     */
    public function it_redeems_a_card_in_the_display_currency_converted_into_the_orders_currency(): void
    {
        $giftCard = $this->createEnabledGiftCard('EUROCARD00000001', 4000, currencyCode: 'EUR');
        $cart = $this->cart();
        $this->addItem($cart, 'MUG', 5000);

        self::assertCount(0, $this->validateAgainstCart($giftCard), 'the channel offers euros, so the card should be accepted');

        $this->applicator()->apply($cart, $giftCard);

        self::assertSame(5000, $cart->getTotal(), 'redeeming should not change what the order costs');
        self::assertSame(5000, $this->redemptionMethod()->getCoveredAmount($cart), 'the card should cover the order in dollars');

        $this->placeOrder($cart);

        self::assertSame(0, $giftCard->getAmount(), 'placing the order should take the 40.00 EUR off the card');
        self::assertSame(OrderPaymentStates::STATE_PAID, $cart->getPaymentState());

        $payment = $cart->getLastPayment(PaymentInterface::STATE_COMPLETED);
        self::assertNotNull($payment, 'the card should have paid for the order');
        self::assertSame(5000, $payment->getAmount());
        self::assertSame('USD', $payment->getCurrencyCode());
    }

    /**
     * 20.00 EUR is $25.00 of the $50.00 order
     *
     * @test
     */
    public function it_covers_part_of_the_order_with_a_smaller_card_in_the_display_currency(): void
    {
        $giftCard = $this->createEnabledGiftCard('EUROCARD00000002', 2000, currencyCode: 'EUR');
        $cart = $this->cart();
        $this->addItem($cart, 'MUG', 5000);

        $this->applicator()->apply($cart, $giftCard);

        self::assertSame(2500, $this->redemptionMethod()->getCoveredAmount($cart));
    }

    /**
     * Refunding the gift card payment gives the card back exactly the 40.00 EUR it gave, not the $50.00 payment
     * converted back at whatever the exchange rate is by then
     *
     * @test
     */
    public function it_gives_a_card_in_the_display_currency_back_exactly_what_it_gave_when_its_payment_is_refunded(): void
    {
        $giftCard = $this->createEnabledGiftCard('EUROCARD00000003', 4000, currencyCode: 'EUR');
        $cart = $this->cart();
        $this->addItem($cart, 'MUG', 5000);
        $this->applicator()->apply($cart, $giftCard);
        $this->placeOrder($cart);
        self::assertSame(0, $giftCard->getAmount(), 'precondition: the card paid the order');

        // the euro has gained on the dollar since
        $exchangeRate = $this->manager->getRepository(ExchangeRate::class)->findOneBy([]);
        self::assertInstanceOf(ExchangeRate::class, $exchangeRate);
        $exchangeRate->setRatio(0.5);
        $this->manager->flush();

        $payment = $cart->getLastPayment(PaymentInterface::STATE_COMPLETED);
        self::assertNotNull($payment);

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        $this->manager->flush();

        self::assertSame(4000, $giftCard->getAmount());
    }

    /** @test */
    public function it_redeems_a_base_currency_card_while_the_shopper_browses_in_another_currency(): void
    {
        $giftCard = $this->createEnabledGiftCard('BASECURRENCY0001', 20000, currencyCode: 'USD');
        $cart = $this->cart();
        $this->addItem($cart, 'MUG', 10000);

        self::assertCount(0, $this->validateAgainstCart($giftCard));

        $this->applicator()->apply($cart, $giftCard);

        self::assertTrue($cart->hasGiftCard($giftCard));
        self::assertSame(10000, $cart->getTotal());
        self::assertSame(10000, $this->redemptionMethod()->getCoveredAmount($cart), 'the card should cover the order one to one');

        $this->placeOrder($cart);

        self::assertSame(10000, $giftCard->getAmount(), 'placing the order should take what the card covered off its balance');

        $payment = $cart->getLastPayment(PaymentInterface::STATE_COMPLETED);
        self::assertNotNull($payment, 'the card should have paid for the order');
        self::assertSame(10000, $payment->getAmount());
        // Sylius' own convention, which the gift card payment follows: a payment is in the currency of its order
        self::assertSame('USD', $payment->getCurrencyCode());
    }

    /**
     * A card in a currency the channel does not offer has no exchange rate the shop keeps for this channel's shoppers,
     * so it cannot pay for the order
     *
     * @test
     */
    public function it_refuses_a_card_in_a_currency_the_channel_does_not_offer(): void
    {
        $pound = new Currency();
        $pound->setCode('GBP');
        $this->manager->persist($pound);

        $giftCard = $this->createEnabledGiftCard('POUNDCARD0000001', 20000, currencyCode: 'GBP');
        $cart = $this->cart();
        $this->addItem($cart, 'MUG', 10000);

        // the card is enabled, unexpired, holds a balance and belongs to the cart's channel, so its currency is the
        // only thing the rule can object to (which message the shopper is shown for it is not this test's concern)
        self::assertCount(1, $this->validateAgainstCart($giftCard));

        $cart->addGiftCard($giftCard);
        self::assertSame(0, $this->redemptionMethod()->getCoveredAmount($cart), 'the card should not count towards the order');
        $cart->removeGiftCard($giftCard);

        $this->expectException(GiftCardCurrencyMismatchException::class);
        $this->applicator()->apply($cart, $giftCard);
    }

    /**
     * What the currency switcher in the shop does: it remembers the chosen currency for the channel, which is where
     * the currency context reads it from on every later request
     */
    private function browseInEuro(): void
    {
        $request = Request::create('http://shop.example.test/en_US/cart/');
        $request->attributes->set('_locale', 'en_US');
        $request->setSession(new Session(new MockArraySessionStorage()));

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        /** @var CurrencyStorageInterface $currencyStorage */
        $currencyStorage = self::getContainer()->get('sylius.storage.currency');
        $currencyStorage->set($this->getChannel(), 'EUR');

        /** @var CurrencyContextInterface $currencyContext */
        $currencyContext = self::getContainer()->get('sylius.context.currency');
        self::assertSame('EUR', $currencyContext->getCurrencyCode(), 'precondition: the shopper browses in EUR');
    }

    /**
     * The cart the shop hands this shopper, i.e. the one every add to cart and redemption request works on
     */
    private function cart(): Order
    {
        /** @var CartContextInterface $cartContext */
        $cartContext = self::getContainer()->get('sylius.context.cart');

        $cart = $cartContext->getCart();
        self::assertInstanceOf(Order::class, $cart);
        self::assertSame('USD', $cart->getCurrencyCode(), 'precondition: Sylius keeps the cart in the channel base currency');

        return $cart;
    }

    private function placeOrder(Order $cart): void
    {
        $customer = new Customer();
        $customer->setEmail('shopper@example.com');
        $this->manager->persist($customer);

        $cart->setCustomer($customer);
        // the card covers the whole order, so there is no payment left for the customer to pick
        $cart->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SKIPPED);
        $this->manager->persist($cart);
        $this->manager->flush();

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($cart, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->manager->flush();
    }

    /**
     * Runs the redemption rule the shop's gift card field is validated with, which checks the card against the
     * shopper's current cart
     */
    private function validateAgainstCart(GiftCardInterface $giftCard): ConstraintViolationListInterface
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        return $validator->validate($giftCard, new GiftCardIsEligible());
    }

    private function handler(): CartGiftCardHandlerInterface
    {
        /** @var CartGiftCardHandlerInterface $handler */
        $handler = self::getContainer()->get(CartGiftCardHandlerInterface::class);

        return $handler;
    }

    private function processor(): OrderProcessorInterface
    {
        /** @var OrderProcessorInterface $processor */
        $processor = self::getContainer()->get('sylius.order_processing.order_processor');

        return $processor;
    }

    private function operator(): OrderGiftCardOperatorInterface
    {
        /** @var OrderGiftCardOperatorInterface $operator */
        $operator = self::getContainer()->get(OrderGiftCardOperatorInterface::class);

        return $operator;
    }

    private function applicator(): GiftCardApplicatorInterface
    {
        /** @var GiftCardApplicatorInterface $applicator */
        $applicator = self::getContainer()->get(GiftCardApplicatorInterface::class);

        return $applicator;
    }

    private function redemptionMethod(): GiftCardRedemptionMethodInterface
    {
        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        return $redemptionMethod;
    }
}
