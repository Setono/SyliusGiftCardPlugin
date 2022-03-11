<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UseSameInputPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var bool $useSameInputForGiftCardAndCoupon */
        $useSameInputForGiftCardAndCoupon = $container->getParameter(
            'setono_sylius_gift_card.cart.use_same_input_for_promotion_and_gift_card'
        );

        if (!$useSameInputForGiftCardAndCoupon) {
            if ($container->hasDefinition('setono_sylius_gift_card.applicator.promotion')) {
                $container->removeDefinition('setono_sylius_gift_card.applicator.promotion');
            }
        }
    }
}
