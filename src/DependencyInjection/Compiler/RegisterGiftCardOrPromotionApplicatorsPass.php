<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler;

use Setono\SyliusGiftCardPlugin\Applicator\GiftCardOrPromotionApplicatorInterface;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Compiler\PrioritizedCompositeServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterGiftCardOrPromotionApplicatorsPass extends PrioritizedCompositeServicePass
{
    public const APPLICATOR_SERVICE_TAG = 'setono_sylius_gift_card.gift_card_or_promotion_applicator';

    public function __construct()
    {
        parent::__construct(
            'setono_sylius_gift_card.applicator.gift_card_or_promotion',
            'setono_sylius_gift_card.applicator.gift_card_or_promotion.composite',
            self::APPLICATOR_SERVICE_TAG,
            'addApplicator'
        );
    }

    public function process(ContainerBuilder $container): void
    {
        parent::process($container);

        $container->setAlias(
            GiftCardOrPromotionApplicatorInterface::class,
            'setono_sylius_gift_card.gift_card_or_promotion_applicator.applicator'
        );
    }
}
