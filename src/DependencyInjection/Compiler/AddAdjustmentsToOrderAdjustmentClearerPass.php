<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AddAdjustmentsToOrderAdjustmentClearerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('sylius.order_processing.adjustment_clearing_types')) {
            return;
        }

        $adjustmentsToRemove = $container->getParameter('sylius.order_processing.adjustment_clearing_types');
        \assert(\is_array($adjustmentsToRemove));

        $adjustmentsToRemove[] = AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT;

        $container->setParameter('sylius.order_processing.adjustment_clearing_types', $adjustmentsToRemove);
    }
}
