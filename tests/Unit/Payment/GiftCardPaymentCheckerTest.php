<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentChecker;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;

/**
 * Gift card payments live on the order next to the gateway payments, and the payment processors, the paid amount
 * calculator and the rollback all tell the two apart by asking this checker, which goes by the configured payment
 * method code
 */
final class GiftCardPaymentCheckerTest extends TestCase
{
    private const GIFT_CARD_CODE = 'gift_card';

    /** @test */
    public function it_recognises_a_payment_made_with_the_gift_card_payment_method(): void
    {
        $checker = new GiftCardPaymentChecker(self::GIFT_CARD_CODE);

        self::assertTrue($checker->isGiftCardPayment($this->paymentWith(self::GIFT_CARD_CODE)));
    }

    /**
     * @test
     *
     * @dataProvider otherPaymentMethodCodes
     */
    public function it_does_not_take_a_payment_made_with_another_method_for_a_gift_card_payment(string $code): void
    {
        $checker = new GiftCardPaymentChecker(self::GIFT_CARD_CODE);

        self::assertFalse($checker->isGiftCardPayment($this->paymentWith($code)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherPaymentMethodCodes(): iterable
    {
        yield 'a gateway method' => ['cash_on_delivery'];
        yield 'a method whose code merely starts with the gift card code' => [self::GIFT_CARD_CODE . '_2'];
    }

    /**
     * An application can rename the gift card payment method, and the checker has to follow the configured code
     * rather than a code of its own
     *
     * @test
     */
    public function it_goes_by_the_configured_payment_method_code(): void
    {
        $checker = new GiftCardPaymentChecker('store_credit');

        self::assertTrue($checker->isGiftCardPayment($this->paymentWith('store_credit')));
        self::assertFalse($checker->isGiftCardPayment($this->paymentWith(self::GIFT_CARD_CODE)));
    }

    /**
     * A cart payment has no method until the customer picks one
     *
     * @test
     */
    public function it_does_not_take_a_payment_without_a_method_for_a_gift_card_payment(): void
    {
        $checker = new GiftCardPaymentChecker(self::GIFT_CARD_CODE);

        self::assertFalse($checker->isGiftCardPayment(new Payment()));
    }

    private function paymentWith(string $methodCode): PaymentInterface
    {
        $method = new PaymentMethod();
        $method->setCode($methodCode);

        $payment = new Payment();
        $payment->setMethod($method);

        return $payment;
    }
}
