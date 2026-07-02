<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\OrderProcessor;

use Setono\SyliusGiftCardPlugin\Calculator\GiftCardCoverageCalculatorInterface;
use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * Converts each applied gift card into a negative order adjustment. Gift card adjustments are cleared and
 * recomputed on every processing run (see AddAdjustmentsToOrderAdjustmentClearerPass), so this only runs in
 * the "adjustment" redemption mode
 */
final class GiftCardAdjustmentProcessor implements OrderProcessorInterface
{
    /**
     * @param AdjustmentFactoryInterface<AdjustmentInterface> $adjustmentFactory
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdjustmentFactoryInterface $adjustmentFactory,
        private readonly GiftCardCoverageCalculatorInterface $coverageCalculator,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if ($order->isEmpty() || !$order->hasGiftCards()) {
            return;
        }

        $label = $this->translator->trans('setono_sylius_gift_card.ui.gift_card');

        foreach ($this->coverageCalculator->calculate($order)->getEntries() as $entry) {
            $amount = $entry['amount'];
            if ($amount <= 0) {
                continue;
            }

            $adjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT,
                $label,
                -1 * $amount,
            );
            $adjustment->setOriginCode($entry['giftCard']->getCode());

            $order->addAdjustment($adjustment);
        }
    }
}
