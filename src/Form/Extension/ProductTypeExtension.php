<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('giftCard', CheckboxType::class, [
            'required' => false,
            'label' => 'setono_sylius_gift_card.form.product.gift_card',
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            ProductType::class,
        ];
    }
}
