<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Model\AdjustmentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webmozart\Assert\Assert;

final class AddAdjustmentsToOrderAdjustmentClearerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('sylius.order_processing.order_adjustments_clearer')) {
            return;
        }

        $clearerDefinition = $container->getDefinition('sylius.order_processing.order_adjustments_clearer');

        $adjustmentsToRemove = $clearerDefinition->getArgument(0);
        Assert::isArray($adjustmentsToRemove);

        $adjustmentsToRemove[] = AdjustmentInterface::ORDER_GIFT_CARD_ADJUSTMENT;

        $clearerDefinition->setArgument(0, $adjustmentsToRemove);
    }
}
