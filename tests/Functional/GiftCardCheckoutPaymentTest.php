<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Checker\OrderPaymentMethodSelectionRequirementCheckerInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface as BasePaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

/**
 * The plugin decorates the Sylius services the checkout asks what the customer still has to pay and how: the
 * checkout payment processor, the payment methods resolver, the default payment method resolver and the payment
 * method selection requirement checker. Each is unit tested on its own; this proves they are the services Sylius
 * actually ends up using, by running a real cart through Sylius' order processing with the gift card payment
 * method enabled in the channel next to an ordinary one
 */
final class GiftCardCheckoutPaymentTest extends GiftCardFunctionalTestCase
{
    private const ORDER_TOTAL = 10000;

    protected function setUp(): void
    {
        parent::setUp();

        // The gift card method is created first, so it sorts first among the channel's payment methods and is what
        // Sylius itself would pick as the default one
        $this->giftCardPaymentMethodProvider()->getPaymentMethod($this->getChannel());
        $this->createCashPaymentMethod();
        $this->manager->flush();

        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.payment_method');
        self::assertSame(
            ['gift_card', 'cash'],
            self::codesOf($repository->findEnabledForChannel($this->getChannel())),
            'the gift card payment method has to come first for this test to prove anything',
        );
    }

    /** @test */
    public function the_cart_payment_goes_to_an_ordinary_payment_method_for_the_full_total_without_gift_cards(): void
    {
        $order = $this->createCart();

        self::assertSame(self::ORDER_TOTAL, $order->getTotal());
        self::assertSame(['cash' => self::ORDER_TOTAL], $this->cartPayments($order));
    }

    /** @test */
    public function applying_a_gift_card_cuts_the_cart_payment_down_to_what_is_left_to_pay(): void
    {
        $order = $this->createCart();

        $this->redemptionMethod()->apply($order, $this->createGiftCard('CHECKOUTPAY00001', 6000));

        // A gift card pays for the order rather than discounting it, so the order still costs what it did
        self::assertSame(self::ORDER_TOTAL, $order->getTotal());
        self::assertSame(['cash' => 4000], $this->cartPayments($order));
        self::assertTrue($this->selectionRequirementChecker()->isPaymentMethodSelectionRequired($order));
    }

    /** @test */
    public function a_gift_card_covering_the_whole_cart_leaves_nothing_to_pay_and_no_payment_step(): void
    {
        $order = $this->createCart();

        $this->redemptionMethod()->apply($order, $this->createGiftCard('CHECKOUTPAY00002', 15000));

        self::assertSame(self::ORDER_TOTAL, $order->getTotal());
        self::assertSame([], $this->cartPayments($order));
        self::assertFalse($this->selectionRequirementChecker()->isPaymentMethodSelectionRequired($order));
    }

    /** @test */
    public function gift_cards_stack_until_the_whole_cart_is_covered(): void
    {
        $order = $this->createCart();
        $first = $this->createGiftCard('CHECKOUTPAY00003', 3000);
        $second = $this->createGiftCard('CHECKOUTPAY00004', 9000);

        $this->redemptionMethod()->apply($order, $first);
        self::assertSame(['cash' => 7000], $this->cartPayments($order));

        $this->redemptionMethod()->apply($order, $second);
        self::assertSame([], $this->cartPayments($order));

        // the second card is only charged what the first one left, not its whole balance
        self::assertSame(self::ORDER_TOTAL, $this->redemptionMethod()->getCoveredAmount($order));
        self::assertSame(3000, $this->redemptionMethod()->getCoveredAmountByGiftCard($order, $first));
        self::assertSame(7000, $this->redemptionMethod()->getCoveredAmountByGiftCard($order, $second));
    }

    /** @test */
    public function removing_the_gift_card_puts_the_full_total_back_on_the_cart_payment(): void
    {
        $order = $this->createCart();
        $giftCard = $this->createGiftCard('CHECKOUTPAY00005', 15000);

        $this->redemptionMethod()->apply($order, $giftCard);
        self::assertSame([], $this->cartPayments($order));

        $this->redemptionMethod()->remove($order, $giftCard);

        self::assertFalse($order->hasGiftCard($giftCard));
        self::assertSame(['cash' => self::ORDER_TOTAL], $this->cartPayments($order));
        self::assertTrue($this->selectionRequirementChecker()->isPaymentMethodSelectionRequired($order));
    }

    /** @test */
    public function the_payment_step_never_offers_the_gift_card_payment_method(): void
    {
        $order = $this->createCart();
        $cartPayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        self::assertNotNull($cartPayment);

        /** @var PaymentMethodsResolverInterface $resolver */
        $resolver = self::getContainer()->get('sylius.payment_methods_resolver');

        self::assertSame(['cash'], self::codesOf($resolver->getSupportedMethods($cartPayment)));
    }

    /**
     * A channel can let the customer skip the payment step when there is only one payment method to choose. The
     * gift card payment method is enabled in the channel too, and must not count as a second choice
     *
     * @test
     */
    public function the_payment_step_is_still_skipped_when_the_ordinary_method_is_the_only_one_to_choose(): void
    {
        $this->getChannel()->setSkippingPaymentStepAllowed(true);
        $order = $this->createCart();

        self::assertFalse($this->selectionRequirementChecker()->isPaymentMethodSelectionRequired($order));
    }

    /**
     * A cart with a single ordinary item costing ORDER_TOTAL, processed the way Sylius processes a cart, so it
     * carries the cart payment Sylius sizes to the full total
     */
    private function createCart(): Order
    {
        $channel = $this->getChannel();

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Mug');
        $product->setSlug('mug');
        $this->manager->persist($product);

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode($channel->getCode());
        $channelPricing->setPrice(self::ORDER_TOTAL);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MUG_VARIANT');
        $variant->setName('Mug');
        $variant->setShippingRequired(false);
        $variant->addChannelPricing($channelPricing);
        $product->addVariant($variant);
        $this->manager->persist($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        new OrderItemUnit($item);

        $order = new Order();
        $order->setChannel($channel);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->addItem($item);

        $this->orderProcessor()->process($order);

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    /**
     * The payments the customer is still asked to make, as payment method code => amount
     *
     * @return array<string, int|null>
     */
    private function cartPayments(Order $order): array
    {
        $payments = [];
        foreach ($order->getPayments() as $payment) {
            if (PaymentInterface::STATE_CART === $payment->getState()) {
                $payments[(string) $payment->getMethod()?->getCode()] = $payment->getAmount();
            }
        }

        return $payments;
    }

    /**
     * @param array<array-key, mixed> $methods
     *
     * @return list<string|null>
     */
    private static function codesOf(array $methods): array
    {
        $codes = [];
        foreach ($methods as $method) {
            self::assertInstanceOf(BasePaymentMethodInterface::class, $method);
            $codes[] = $method->getCode();
        }

        return $codes;
    }

    private function createGiftCard(string $code, int $amount): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    private function createCashPaymentMethod(): void
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.payment_method');

        $paymentMethod = $factory->createWithGateway('offline');
        $paymentMethod->setCode('cash');
        $paymentMethod->setEnabled(true);
        $paymentMethod->getGatewayConfig()?->setGatewayName('cash');
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('Cash');
        $paymentMethod->addChannel($this->getChannel());

        $this->manager->persist($paymentMethod);
    }

    private function giftCardPaymentMethodProvider(): GiftCardPaymentMethodProviderInterface
    {
        /** @var GiftCardPaymentMethodProviderInterface $provider */
        $provider = self::getContainer()->get(GiftCardPaymentMethodProviderInterface::class);

        return $provider;
    }

    private function redemptionMethod(): GiftCardRedemptionMethodInterface
    {
        /** @var GiftCardRedemptionMethodInterface $redemptionMethod */
        $redemptionMethod = self::getContainer()->get('setono_sylius_gift_card.redemption_method');

        return $redemptionMethod;
    }

    private function orderProcessor(): OrderProcessorInterface
    {
        /** @var OrderProcessorInterface $orderProcessor */
        $orderProcessor = self::getContainer()->get('sylius.order_processing.order_processor');

        return $orderProcessor;
    }

    private function selectionRequirementChecker(): OrderPaymentMethodSelectionRequirementCheckerInterface
    {
        /** @var OrderPaymentMethodSelectionRequirementCheckerInterface $checker */
        $checker = self::getContainer()->get('sylius.checker.order_payment_method_selection_requirement');

        return $checker;
    }
}
