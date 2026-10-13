<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\StateResolver;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardPaidAmountCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;

/**
 * Decorates Sylius' order payment state resolver, so an order the gift cards pay only in part keeps awaiting payment
 * until the rest is paid, and is authorized once the rest is.
 *
 * The gift card payments are completed the moment the order is placed, and Sylius calls an order partially paid as
 * soon as any of its payments is completed. But Sylius' shop only lets a customer pay for an order, or change how to
 * pay it, while the order awaits payment: on the order page a guest reaches from the thank you page, and where a
 * declined gateway payment sends the customer to try again, and on the order in the customer's account. Sylius also
 * only expires unpaid orders that await payment. So a customer whose gift card paid part of the order could neither
 * pay the rest nor try again, and an order nobody ever paid would keep the gift card's balance for good.
 *
 * While the only payments that have gone through are gift card payments that do not cover the order, the order's
 * payment state is left as it is. A payment provider that captures later authorizes the rest rather than paying it,
 * and the gift card payments and the authorization then secure the whole order. Sylius weighs completed payments
 * before authorized ones, so it would call the order partially paid, which whatever waits for authorized orders (a
 * warehouse upload, the order confirmation) skips. The order is authorized instead, as it would be without a gift
 * card, and capturing the rest pays it (#499). Otherwise, once a payment of the customer's own has gone through, or
 * the gift cards cover the whole order, Sylius resolves the state as it always does
 */
final class GiftCardAwareOrderPaymentStateResolver implements StateResolverInterface
{
    /**
     * The states of a payment that has moved money (or, authorized, has it promised). A payment that is still new or
     * processing, or that failed or was cancelled, has not paid anything
     */
    private const GONE_THROUGH = [
        PaymentInterface::STATE_AUTHORIZED,
        PaymentInterface::STATE_COMPLETED,
        PaymentInterface::STATE_REFUNDED,
    ];

    public function __construct(
        private readonly StateResolverInterface $decorated,
        private readonly GiftCardPaidAmountCalculatorInterface $paidAmountCalculator,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
        private readonly StateMachineInterface $stateMachine,
    ) {
    }

    public function resolve(BaseOrderInterface $order): void
    {
        if ($order instanceof OrderInterface) {
            if ($this->isPaidInPartByGiftCardsOnly($order)) {
                return;
            }

            if ($this->isAuthorizedForTheRest($order)) {
                // An order authorized already stays so, rather than becoming partially paid
                if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_AUTHORIZE)) {
                    $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_AUTHORIZE);
                }

                return;
            }
        }

        $this->decorated->resolve($order);
    }

    private function isPaidInPartByGiftCardsOnly(OrderInterface $order): bool
    {
        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface || $this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            if (in_array($payment->getState(), self::GONE_THROUGH, true)) {
                return false;
            }
        }

        $paidByGiftCards = $this->paidAmountCalculator->getPaidAmount($order);

        return 0 < $paidByGiftCards && $paidByGiftCards < $order->getTotal();
    }

    /**
     * Whether the gift cards have paid part of the order and authorized payments, not captured yet, cover the rest.
     * Once a payment of the customer's own is completed or refunded, the order is Sylius' to resolve again
     */
    private function isAuthorizedForTheRest(OrderInterface $order): bool
    {
        $authorized = 0;
        foreach ($order->getPayments() as $payment) {
            if (!$payment instanceof PaymentInterface || $this->paymentChecker->isGiftCardPayment($payment)) {
                continue;
            }

            if (in_array($payment->getState(), [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_REFUNDED], true)) {
                return false;
            }

            if (PaymentInterface::STATE_AUTHORIZED === $payment->getState()) {
                $authorized += (int) $payment->getAmount();
            }
        }

        $paidByGiftCards = $this->paidAmountCalculator->getPaidAmount($order);

        return 0 < $paidByGiftCards && $paidByGiftCards < $order->getTotal() && $paidByGiftCards + $authorized >= $order->getTotal();
    }
}
