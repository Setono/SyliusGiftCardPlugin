<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Extension;

use Sylius\Bundle\OrderBundle\Form\Type\CartType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class CartTypeExtension extends AbstractTypeExtension
{
    private bool $useSameInputForGiftCardAndPromotion;

    public function __construct(bool $useSameInputForGiftCardAndPromotion)
    {
        $this->useSameInputForGiftCardAndPromotion = $useSameInputForGiftCardAndPromotion;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($this->useSameInputForGiftCardAndPromotion) {
            $builder->remove('promotionCoupon');
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [CartType::class];
    }
}
