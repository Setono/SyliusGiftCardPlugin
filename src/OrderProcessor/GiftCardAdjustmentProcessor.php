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
 * Converts each applied gift card into a negative order adjustment, so this only runs in the "adjustment"
 * redemption mode. Adjustments are cleared and recomputed on every processing run
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

        // Sylius' own adjustments clearer can only be told about this adjustment type from 1.14.2 onwards,
        // where the sylius.order_processing.adjustment_clearing_types parameter was introduced. Clearing what
        // we previously added ourselves keeps the recomputation correct on every supported version, and has to
        // happen before the early return below so adjustments do not survive removing the last gift card.
        $order->removeAdjustmentsRecursively(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT);

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
