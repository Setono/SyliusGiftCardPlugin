<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderGiftCardProcessor implements OrderProcessorInterface
{
    /** @var TranslatorInterface */
    private $translator;

    /** @var AdjustmentFactoryInterface */
    private $adjustmentFactory;

    public function __construct(
        TranslatorInterface $translator,
        AdjustmentFactoryInterface $adjustmentFactory
    ) {
        $this->translator = $translator;
        $this->adjustmentFactory = $adjustmentFactory;
    }

    public function process(BaseOrderInterface $order): void
    {
        /** @var OrderInterface $order */
        Assert::isInstanceOf($order, OrderInterface::class);

        if ($order->isEmpty()) {
            return;
        }

        if (!$order->hasGiftCards()) {
            return;
        }

        foreach ($order->getGiftCards() as $giftCard) {
            $amount = $giftCard->getAmount();
            $total = $order->getTotal();

            if ($total < $amount) {
                $amount = $total;
            }

            if (0 === $amount) {
                continue;
            }

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $this->translator->trans('setono_sylius_gift_card.ui.gift_card') . ': ' . $giftCard->getCode(),
                -1 * $amount
            );

            $adjustment->setOriginCode($giftCard->getCode());

            $order->addAdjustment($adjustment);
        }
    }
}
