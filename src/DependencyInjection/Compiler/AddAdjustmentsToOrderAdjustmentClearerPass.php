<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the gift card adjustment type with Sylius' order adjustments clearer so that gift card
 * adjustments are cleared and recomputed on every order processing run
 */
final class AddAdjustmentsToOrderAdjustmentClearerPass implements CompilerPassInterface
{
    private const PARAMETER = 'sylius.order_processing.adjustment_clearing_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        /** @var list<string> $types */
        $types = (array) $container->getParameter(self::PARAMETER);

        if (!in_array(AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT, $types, true)) {
            $types[] = AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT;
            $container->setParameter(self::PARAMETER, $types);
        }
    }
}
