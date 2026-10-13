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
 * and the gift card payments and the authorizations then secure the whole order. Sylius weighs completed payments
 * before authorized ones, so it would call the order partially paid, which whatever waits for authorized orders (a
 * warehouse upload, the order confirmation) skips, and from which it could not be authorized any more. So while the
 * customer's own payments are at most authorized, the order is not Sylius' to resolve: it is authorized once the
 * gift cards and the authorizations cover it, as it would be without a gift card, and partially authorized until then,
 * whatever order the payments come in (#499). Capturing the rest pays it. Otherwise, once a payment of the customer's
 * own is completed or refunded, or the gift cards cover the whole order or nothing of it, Sylius resolves the state as
 * it always does
 */
final class GiftCardAwareOrderPaymentStateResolver implements StateResolverInterface
{
    public function __construct(
        private readonly StateResolverInterface $decorated,
        private readonly GiftCardPaidAmountCalculatorInterface $paidAmountCalculator,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
        private readonly StateMachineInterface $stateMachine,
    ) {
    }

    public function resolve(BaseOrderInterface $order): void
    {
        if (!$order instanceof OrderInterface || $this->hasPaymentOfTheCustomerGoneThrough($order)) {
            $this->decorated->resolve($order);

            return;
        }

        $paidByGiftCards = $this->paidAmountCalculator->getPaidAmount($order);
        if (0 === $paidByGiftCards || $paidByGiftCards >= $order->getTotal()) {
            $this->decorated->resolve($order);

            return;
        }

        // Nothing of the rest has been authorized either, so the order keeps awaiting payment
        $authorized = $this->getAuthorizedAmount($order);
        if (0 === $authorized) {
            return;
        }

        $transition = $paidByGiftCards + $authorized >= $order->getTotal()
            ? OrderPaymentTransitions::TRANSITION_AUTHORIZE
            : OrderPaymentTransitions::TRANSITION_PARTIALLY_AUTHORIZE;

        // An order authorized already stays so, rather than becoming partially paid
        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, $transition);
        }
    }

    /**
     * Whether a payment of the customer's own has moved money: completed, or refunded since
     */
    private function hasPaymentOfTheCustomerGoneThrough(OrderInterface $order): bool
    {
        foreach ($this->getPaymentsOfTheCustomer($order) as $payment) {
            if (in_array($payment->getState(), [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_REFUNDED], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the customer's own payments have authorized and not captured yet
     */
    private function getAuthorizedAmount(OrderInterface $order): int
    {
        $authorized = 0;
        foreach ($this->getPaymentsOfTheCustomer($order) as $payment) {
            if (PaymentInterface::STATE_AUTHORIZED === $payment->getState()) {
                $authorized += (int) $payment->getAmount();
            }
        }

        return $authorized;
    }

    /**
     * @return iterable<PaymentInterface>
     */
    private function getPaymentsOfTheCustomer(OrderInterface $order): iterable
    {
        foreach ($order->getPayments() as $payment) {
            if ($payment instanceof PaymentInterface && !$this->paymentChecker->isGiftCardPayment($payment)) {
                yield $payment;
            }
        }
    }
}
