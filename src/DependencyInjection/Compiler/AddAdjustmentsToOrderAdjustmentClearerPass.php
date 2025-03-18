<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webmozart\Assert\Assert;

final class AddAdjustmentsToOrderAdjustmentClearerPass implements CompilerPassInterface
{
    private const ADJUSTMENT_CLEARING_TYPES = 'sylius.order_processing.adjustment_clearing_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::ADJUSTMENT_CLEARING_TYPES)) {
            return;
        }
        $types = $container->getParameter(self::ADJUSTMENT_CLEARING_TYPES);
        Assert::isArray($types);
        $types[] = AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT;
        $container->setParameter(self::ADJUSTMENT_CLEARING_TYPES, $types);
    }
}
