<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Cart\CartGiftCardHandlerInterface;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardCurrencyMismatchException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsApplicable;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Currency\CurrencyStorageInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A shopper on a USD channel who has switched the shop to EUR. Sylius keeps the order in the channel's base
 * currency all the same: the cart contexts stamp a new cart with the base currency, and the other currencies a
 * channel offers only change how amounts are rendered (the shop's money macro converts from the base currency when
 * printing). Gift card balances are compared one to one with order amounts, so a card bought or redeemed by this
 * shopper has to be issued in, and checked against, the currency the order is kept in, never the one on screen
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

        $this->manager->flush();

        $this->browseInEuro();
    }

    /** @test */
    public function it_issues_a_card_bought_while_browsing_in_another_currency_in_the_base_currency(): void
    {
        $cart = $this->cart();

        $item = $this->addItem($cart, 'GIFT_CARD', 5000, giftCard: true);
        $this->handler()->handle(new AddToCartCommand($cart, $item, new GiftCardInformation(5000)));

        // bumping the quantity in the cart adds a unit without a card, which reconciliation issues one for
        new OrderItemUnit($item);
        $this->manager->persist($cart);
        $this->operator()->reconcile($cart);

        self::assertCount(2, $item->getUnits());
        foreach ($item->getUnits() as $unit) {
            self::assertInstanceOf(OrderItemUnit::class, $unit);

            $giftCard = $unit->getGiftCard();
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            self::assertSame('USD', $giftCard->getCurrencyCode(), 'the card should be issued in the channel base currency');
            self::assertSame(5000, $giftCard->getAmount(), 'the card should hold what the unit costs in that currency');
        }
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
     * A card in the currency the shopper happens to browse in holds an amount in a currency the order is not
     * kept in, so letting it pay one to one for the order would hand out money at the wrong rate
     *
     * @test
     */
    public function it_refuses_a_card_in_the_display_currency_on_an_order_kept_in_the_base_currency(): void
    {
        $giftCard = $this->createEnabledGiftCard('DISPLAYCURRENCY1', 20000, currencyCode: 'EUR');
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

        return $validator->validate($giftCard, new GiftCardIsApplicable());
    }

    private function handler(): CartGiftCardHandlerInterface
    {
        /** @var CartGiftCardHandlerInterface $handler */
        $handler = self::getContainer()->get(CartGiftCardHandlerInterface::class);

        return $handler;
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
