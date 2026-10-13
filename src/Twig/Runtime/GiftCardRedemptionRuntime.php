<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverage;
use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Payment\GiftCardPaymentCheckerInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardRedemptionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly GiftCardRedemptionMethodInterface $redemptionMethod,
        private readonly FormFactoryInterface $formFactory,
        private readonly GiftCardPaymentCheckerInterface $paymentChecker,
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
    ) {
    }

    /**
     * The currency a gift card bought in the channel now is held in, and the amount field is in, so the product page's
     * live preview formats the amount in it
     */
    public function getCurrencyCode(ChannelInterface $channel): string
    {
        return $this->currencyProvider->getCurrencyCode($channel);
    }

    public function createApplyForm(): FormView
    {
        return $this->formFactory->create(AddGiftCardToOrderType::class, new AddGiftCardToOrderCommand(), [
            'action' => '',
        ])->createView();
    }

    /**
     * What each gift card on the order pays, in the order's currency, and what that takes off the card, in the card's,
     * so the cart can show a card held in the currency the customer browses in by its own amount. On a placed order, what
     * the cards paid (#498)
     */
    public function getCoverage(OrderInterface $order): GiftCardCoverage
    {
        return $this->redemptionMethod->getCoverage($order);
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

    /**
     * Whether the given payment method is the one gift card payments are made with, so the admin's payment method form
     * can explain it
     */
    public function isGiftCardPaymentMethod(?PaymentMethodInterface $paymentMethod): bool
    {
        return null !== $paymentMethod && $this->paymentChecker->isGiftCardPaymentMethod($paymentMethod);
    }
}
