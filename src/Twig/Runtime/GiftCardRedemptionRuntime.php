<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardRedemptionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly GiftCardRedemptionMethodInterface $redemptionMethod,
        private readonly FormFactoryInterface $formFactory,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
    ) {
    }

    public function createApplyForm(): FormView
    {
        return $this->formFactory->create(AddGiftCardToOrderType::class, new AddGiftCardToOrderCommand(), [
            'action' => '',
        ])->createView();
    }

    public function getCoveredAmount(OrderInterface $order): int
    {
        return $this->redemptionMethod->getCoveredAmount($order);
    }

    public function getCoveredAmountByGiftCard(OrderInterface $order, GiftCardInterface $giftCard): int
    {
        return $this->redemptionMethod->getCoveredAmountByGiftCard($order, $giftCard);
    }

    /**
     * The amount still to be paid by other means after the gift cards have been applied
     */
    public function getRemainingTotal(OrderInterface $order): int
    {
        return max(0, $order->getTotal() - $this->getCoveredAmount($order));
    }

    /**
     * The payment for what the gift cards do not pay: the order's last payment that is not a gift card payment, or
     * null when it has none (the gift cards pay the whole order, say). The gift card payments are added when the
     * order is placed, after the payment the customer chose for the rest, so this is not necessarily the order's
     * last payment
     */
    public function getRemainingPayment(BaseOrderInterface $order): ?PaymentInterface
    {
        $remainingPayment = null;
        foreach ($order->getPayments() as $payment) {
            if ($payment instanceof PaymentInterface && !$this->paymentChecker->isGiftCardPayment($payment)) {
                $remainingPayment = $payment;
            }
        }

        return $remainingPayment;
    }
}
