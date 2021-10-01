<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Provider\OrderEligibleTotalProviderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardProcessor implements OrderProcessorInterface
{
    private TranslatorInterface $translator;

    private AdjustmentFactoryInterface $adjustmentFactory;

    private OrderEligibleTotalProviderInterface $orderEligibleTotalProvider;

    public function __construct(
        TranslatorInterface $translator,
        AdjustmentFactoryInterface $adjustmentFactory,
        OrderEligibleTotalProviderInterface $orderEligibleTotalProvider
    ) {
        $this->translator = $translator;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->orderEligibleTotalProvider = $orderEligibleTotalProvider;
    }

    /**
     * @param BaseOrderInterface|OrderInterface $order
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if ($order->isEmpty()) {
            return;
        }

        if (!$order->hasGiftCards()) {
            return;
        }

        foreach ($order->getGiftCards() as $giftCard) {
            $amount = $giftCard->getAmount();
            $total = $this->orderEligibleTotalProvider->getEligibleTotal($order);

            if ($total < $amount) {
                $amount = $total;
            }

            if (0 === $amount) {
                continue;
            }

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $this->translator->trans('setono_sylius_gift_card.ui.gift_card'),
                -1 * $amount
            );

            $adjustment->setOriginCode($giftCard->getCode());

            $order->addAdjustment($adjustment);
        }
    }
}
