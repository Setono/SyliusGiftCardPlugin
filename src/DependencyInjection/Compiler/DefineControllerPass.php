<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This class will be used to create an alias to for `setono_sylius_gift_card.controller.action.add_gift_card_to_order`
 * According to whether we want the same input for GiftCard and PromotionCoupon or not.
 */
final class DefineControllerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $useSameInput = (bool) $container->getParameter('setono_sylius_gift_card.cart.use_same_input_for_promotion_and_gift_card');

        if ($useSameInput) {
            $container->setAlias(
                'setono_sylius_gift_card.controller.action.add_gift_card_to_order',
                'setono_sylius_gift_card.controller.action.add_gift_card_to_order.composed'
            );
        } else {
            $container->setAlias(
                'setono_sylius_gift_card.controller.action.add_gift_card_to_order',
                'setono_sylius_gift_card.controller.action.add_gift_card_to_order.simple'
            );
        }

        $container->getAlias('setono_sylius_gift_card.controller.action.add_gift_card_to_order')->setPublic(true);
    }
}
