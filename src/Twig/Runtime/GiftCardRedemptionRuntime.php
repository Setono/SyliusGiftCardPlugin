<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Twig\Runtime;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

final class GiftCardRedemptionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly GiftCardRedemptionMethodInterface $redemptionMethod,
        private readonly FormFactoryInterface $formFactory,
        private readonly string $redemptionMode,
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

    public function isPaymentMode(): bool
    {
        return 'payment' === $this->redemptionMode;
    }
}
